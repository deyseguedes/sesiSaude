<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;

final class TeamRecognitionService
{
    public function __construct(private readonly Store $store, private readonly array $user, private readonly ?int $company=null) {}
    private function company(): int { return $this->company??(int)$this->user['company_id']; }
    private function manage(): void { (new IndustryService($this->store,$this->user,$this->company()))->gamification(); }
    public function teams(): array
    {
        $company=$this->company();
        if(in_array($this->user['role'],['admin','manager'],true)) {
            $this->manage();
            $teams=$this->store->all('SELECT id,name FROM teams WHERE company_id=? ORDER BY name',[$company]);
        } else {
            if(!in_array($this->user['role'],['doctor','nurse','navigator'],true)) throw new HttpException(403,'FORBIDDEN','Acesso restrito à equipe e gestão autorizada.');
            if($company!==(int)$this->user['company_id']) throw new HttpException(404,'NOT_FOUND','Empresa não encontrada.');
            $teams=$this->store->all('SELECT t.id,t.name FROM teams t JOIN team_members m ON m.team_id=t.id WHERE t.company_id=? AND m.user_id=? ORDER BY t.name',[$company,$this->user['id']]);
        }
        Audit::record($this->store,array_replace($this->user,['company_id'=>$company]),'team_recognition_read','company',$company);
        return ['mode'=>'simulation','individual_points_retired'=>true,'minimum_due'=>300,'teams'=>array_map(fn($t)=>['id'=>(int)$t['id'],'name'=>$t['name'],'status'=>'not_assessable','reason'=>'Carteira congelada, linha de base e travas ainda não consolidadas.'],$teams)];
    }
    public function simulate(array $d): array
    {
        $this->manage();$team=Input::id($d,'team_id');
        if(!$this->store->one('SELECT id FROM teams WHERE id=? AND company_id=?',[$team,$this->company()])) throw new HttpException(404,'NOT_FOUND','Equipe não encontrada.');
        $result=self::calculate($d);
        Audit::record($this->store,array_replace($this->user,['company_id'=>$this->company()]),'team_recognition_simulated','team',$team);
        return ['team_id'=>$team,'mode'=>'simulation','payment_authorized'=>false,'source'=>'manual_scenario']+$result;
    }
    public static function calculate(array $d): array
    {
        foreach(['due_count','excluded_count','on_time_count'] as $key) if(!isset($d[$key]) || !is_int($d[$key]) || $d[$key]<0 || $d[$key]>10000000) Input::fail($key);
        foreach(['baseline_percent','satisfaction_percent'] as $key) if(!isset($d[$key]) || !is_numeric($d[$key]) || !is_finite((float)$d[$key]) || (float)$d[$key]<0 || (float)$d[$key]>100) Input::fail($key);
        $period=Input::choice($d,'period',['quarter','semester','regional']);
        foreach(['frozen_verified','baseline_verified','no_upheld_pressure','risk_audit_passed','consent_compliant'] as $key) if(!array_key_exists($key,$d) || !is_bool($d[$key])) Input::fail($key);
        $denominator=$d['due_count']-$d['excluded_count'];
        if($denominator<0 || $d['on_time_count']>$denominator) Input::fail('contagens');
        if($denominator===0) return ['status'=>'not_assessable','denominator'=>0,'rp_percent'=>null,'gain_pp'=>null,'proposed_percent'=>null,'blockers'=>['Sem retornos devidos válidos.']];
        $rp=100*$d['on_time_count']/$denominator;$gain=round($rp-(float)$d['baseline_percent'],10);
        $band=$rp>=80?5:($gain>=12?5:($gain>=10?4:($gain>=8?3:($gain>=6?2:($gain>=4?1:0)))));
        $blockers=[];
        if($denominator<300) $blockers[]=$period==='quarter'?'Apurar no semestre.':($period==='semester'?'Agrupar na regional.':'Amostra regional insuficiente.');
        foreach(['frozen_verified'=>'Carteira congelada não verificada.','baseline_verified'=>'Linha de base não verificada.','no_upheld_pressure'=>'Queixa de pressão procedente ou verificação pendente.','risk_audit_passed'=>'Auditoria de riscos e exclusões não liberada.','consent_compliant'=>'Autorizações de contato não verificadas.'] as $key=>$message) if(!$d[$key]) $blockers[]=$message;
        if((float)$d['satisfaction_percent']<90) $blockers[]='Satisfação abaixo de 90%.';
        return ['status'=>$blockers?'blocked':'simulated','denominator'=>$denominator,'rp_percent'=>$rp,'baseline_percent'=>(float)$d['baseline_percent'],'gain_pp'=>$gain,'proposed_percent'=>$band,'blockers'=>$blockers,'period'=>$period];
    }
}
