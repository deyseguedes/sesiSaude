<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class IndustryService
{
    public readonly int $company;
    public function __construct(public readonly Store $store,public readonly array $user,?int $company=null)
    {
        $this->company=$company??(int)$user['company_id'];
        if($this->company!==(int)$user['company_id'] && (!$this->isPlatform() || !$store->one('SELECT company_id FROM management_company_access WHERE user_id=? AND company_id=?',[$user['id'],$this->company]))) throw new HttpException(404,'NOT_FOUND','Empresa não encontrada.');
    }
    public function isPlatform(): bool { return $this->user['role']==='admin' && ($this->user['management_level']??'none')==='platform'; }
    public function manage(string $resource='administration'): void
    {
        if($this->isPlatform()) return;
        $level=$this->user['management_level']??'none';
        if($resource==='accounts' || $resource==='companies') throw new HttpException(403,'FORBIDDEN','Permissão de administração da plataforma necessária.');
        if($resource==='care_operations' && $level==='coordination' && in_array($this->user['role'],['doctor','nurse'],true)) return;
        if($resource!=='care_operations' && in_array($level,['company','coordination'],true)) return;
        throw new HttpException(403,'FORBIDDEN','Nível de gestão insuficiente para este recurso.');
    }
    public function audit(string $event,string $resource,int $id): void { Audit::record($this->store,array_replace($this->user,['company_id'=>$this->company]),$event,$resource,$id); }
    public function gamification(): array
    {
        if(!$this->isPlatform() && !($this->user['role']==='manager' && ($this->user['management_level']??'none')==='company')) throw new HttpException(403,'FORBIDDEN','A adoção da gamificação é definida pela gestão empresarial ou administrador SESI autorizado.');
        $row=$this->store->one('SELECT id,gamification_enabled,gamification_version,reactivation_enabled,reactivation_days,reactivation_points,reactivation_policy_at FROM companies WHERE id=?',[$this->company]);
        if(!$row) throw new HttpException(404,'NOT_FOUND','Empresa não encontrada.');
        return ['company_id'=>(int)$row['id'],'enabled'=>(bool)$row['gamification_enabled'],'version'=>(int)$row['gamification_version'],'recognition_mode'=>'team_simulation','reactivation_enabled'=>false,'reactivation_days'=>(int)$row['reactivation_days'],'reactivation_points'=>(int)$row['reactivation_points'],'reactivation_effective_at'=>$row['reactivation_policy_at']];
    }
    public function setGamification(array $d): array
    {
        $old=$this->gamification();$enabled=self::flag($d,'enabled');$version=Input::id($d,'version');
        if(array_key_exists('reactivation_enabled',$d) && self::flag($d,'reactivation_enabled')) throw new HttpException(422,'INDIVIDUAL_RECOGNITION_RETIRED','O bônus individual foi substituído pelo reconhecimento coletivo da equipe.');
        $d['reactivation_enabled']=false;
        $reactivation=array_key_exists('reactivation_enabled',$d)?self::flag($d,'reactivation_enabled'):(int)$old['reactivation_enabled'];
        $days=$d['reactivation_days']??$old['reactivation_days'];$points=$d['reactivation_points']??$old['reactivation_points'];
        if(!is_int($days) || $days<1 || $days>3650 || !is_int($points) || $points<1 || $points>1000) Input::fail('período de 1 a 3650 dias e bônus de 1 a 1000 pontos');
        $effective=$old['reactivation_effective_at'];
        if($enabled && $reactivation && (!$old['enabled'] || !$old['reactivation_enabled'] || $old['reactivation_days']!==$days || $old['reactivation_points']!==$points || !$effective)) $effective=date('Y-m-d H:i:s');
        return $this->store->transaction(function()use($enabled,$version,$reactivation,$days,$points,$effective){
            $statement=$this->store->db->prepare('UPDATE companies SET gamification_enabled=?,reactivation_enabled=?,reactivation_days=?,reactivation_points=?,reactivation_policy_at=?,gamification_version=gamification_version+1 WHERE id=? AND gamification_version=?');
            $statement->execute([$enabled,$reactivation,$days,$points,$effective,$this->company,$version]);
            if($statement->rowCount()!==1) throw new HttpException(409,'CONFLICT','Configuração alterada. Atualize antes de tentar novamente.');
            $this->audit($enabled?'gamification_enabled':'gamification_disabled','company',$this->company);
            $this->audit('reactivation_policy_saved','company',$this->company);
            return $this->gamification();
        });
    }
    public static function coordinates(array $d): array
    {
        if(($d['latitude']??null)===null && ($d['longitude']??null)===null) return [null,null];
        $lat=Input::number($d['latitude']??null,'latitude'); $lon=Input::number($d['longitude']??null,'longitude');
        if(abs($lat)>90 || abs($lon)>180) Input::fail('coordenadas'); return [$lat,$lon];
    }
    public static function postal(array $d): string
    {
        $v=Input::text($d,'postal_code',9); if(!preg_match('/^[0-9]{5}-?[0-9]{3}$/D',$v)) Input::fail('CEP'); return $v;
    }
    public static function flag(array $d,string $key): int { if(!is_bool($d[$key]??null)) Input::fail($key); return (int)$d[$key]; }
    public function companies(): array
    {
        $this->manage();
        if(!$this->isPlatform()) return $this->store->all('SELECT id,name FROM companies WHERE id=?',[$this->company]);
        return $this->store->all('SELECT c.id,c.name FROM companies c WHERE c.id=? OR EXISTS (SELECT 1 FROM management_company_access a WHERE a.company_id=c.id AND a.user_id=?) ORDER BY c.name',[$this->user['company_id'],$this->user['id']]);
    }
    public function createCompany(array $d): array
    {
        $this->manage('companies'); $name=Input::text($d,'name',150);
        return $this->store->transaction(function()use($name){$id=$this->store->insert('companies',['name'=>$name]);$this->store->insert('management_company_access',['user_id'=>$this->user['id'],'company_id'=>$id]);$this->audit('industry_created','company',$id);return ['id'=>$id];});
    }
    public function overview(): array
    {
        $this->manage(); $s=$this->store;
        return ['company_id'=>$this->company,'industry_units'=>$s->all('SELECT * FROM industry_units WHERE company_id=? ORDER BY name',[$this->company]),'contracts'=>$s->all('SELECT * FROM service_contracts WHERE company_id=? ORDER BY id DESC',[$this->company]),'beneficiaries'=>$s->all('SELECT b.*,p.name,u.email FROM beneficiary_links b JOIN patients p ON p.id=b.patient_id LEFT JOIN users u ON u.id=p.user_id WHERE b.company_id=? ORDER BY b.id DESC',[$this->company]),'teams'=>$s->all('SELECT id,name FROM teams WHERE company_id=?',[$this->company]),'professionals'=>$s->all('SELECT id,name,role,management_level,active FROM users WHERE company_id=? AND role IN (?,?,?) ORDER BY name',[$this->company,'doctor','nurse','navigator']),'service_units'=>$s->all('SELECT * FROM service_units WHERE company_id=? ORDER BY name',[$this->company]),'accounts'=>$this->isPlatform()?$s->all('SELECT id,name,email,role,management_level,active FROM users WHERE company_id=? ORDER BY name',[$this->company]):[],'invitations'=>$s->all('SELECT id,email,kind,status,expires_at FROM enrollment_invitations WHERE company_id=? ORDER BY id DESC',[$this->company])];
    }
    public function unit(array $d,bool $service=false): array
    {
        $this->manage($service?'care_operations':'administration'); [$lat,$lon]=self::coordinates($d);
        $data=['company_id'=>$this->company,'name'=>Input::text($d,'name',150),'address'=>Input::text($d,'address',300),'postal_code'=>self::postal($d),'latitude'=>$lat,'longitude'=>$lon];
        if($service) $data['kind']=Input::choice($d,'kind',['sesi','industry']);
        $table=$service?'service_units':'industry_units';
        return $this->store->transaction(function()use($table,$data){$id=$this->store->insert($table,$data);$this->audit('unit_created',$table,$id);return ['id'=>$id];});
    }
    public function contract(array $d,?int $id=null): array
    {
        $this->manage(); $start=Input::date($d,'start_date'); $end=Input::date($d,'end_date'); if($end<$start) Input::fail('vigência');
        $data=['company_id'=>$this->company,'name'=>Input::text($d,'name',150),'start_date'=>$start,'end_date'=>$end,'status'=>Input::choice($d,'status',['active','paused']),'allow_in_person'=>self::flag($d,'allow_in_person'),'allow_telehealth'=>self::flag($d,'allow_telehealth'),'allow_dependents'=>self::flag($d,'allow_dependents')];
        if(!$data['allow_in_person'] && !$data['allow_telehealth']) Input::fail('modalidade contratada');
        return $this->store->transaction(function()use($data,$d,$id){
            if($id===null) {$id=$this->store->insert('service_contracts',$data);$event='contract_created';}
            else {
                if(!$this->store->one('SELECT id FROM service_contracts WHERE id=? AND company_id=?',[$id,$this->company])) throw new HttpException(404,'NOT_FOUND','Contrato não encontrado.');
                $version=Input::id($d,'version');$columns=array_keys($data);
                $q=$this->store->db->prepare('UPDATE service_contracts SET '.implode(',',array_map(fn($c)=>$c.'=?',$columns)).',version=version+1 WHERE id=? AND company_id=? AND version=?');$q->execute([...array_values($data),$id,$this->company,$version]);if($q->rowCount()!==1) throw new HttpException(409,'CONFLICT','Contrato alterado. Recarregue.');$event='contract_updated';
            }
            $this->audit($event,'contract',$id);return ['id'=>$id];
        });
    }
    public function activeContract(int $id): array
    {
        $c=$this->store->oneLocked('SELECT * FROM service_contracts WHERE id=? AND company_id=? AND status=? AND start_date<=? AND end_date>=?',[$id,$this->company,'active',date('Y-m-d'),date('Y-m-d')]);
        if(!$c) throw new HttpException(422,'CONTRACT_UNAVAILABLE','Contrato fora da vigência ou indisponível.'); return $c;
    }
    public function account(array $d): array
    {
        $this->manage('accounts'); $name=Input::text($d,'name',150); $email=strtolower(Input::text($d,'email',190)); if(!filter_var($email,FILTER_VALIDATE_EMAIL)) Input::fail('email');
        $password=Input::password($d);
        $role=Input::choice($d,'role',['doctor','nurse','navigator','manager']); $level=Input::choice($d,'management_level',['none','company','coordination']);
        if(($level==='coordination' && !in_array($role,['doctor','nurse'],true)) || ($level==='company' && $role!=='manager')) Input::fail('nível de gestão');
        return $this->store->transaction(function()use($name,$email,$password,$role,$level){$id=$this->store->insert('users',['company_id'=>$this->company,'name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>$role,'management_level'=>$level]);$this->audit('account_created','user',$id);return ['id'=>$id];});
    }
    public function updateAccount(int $id,array $d): array
    {
        $this->manage('accounts'); $u=$this->store->one('SELECT * FROM users WHERE id=? AND company_id=?',[$id,$this->company]);
        if(!$u) throw new HttpException(404,'NOT_FOUND','Usuário não encontrado.');
        if($id===(int)$this->user['id'] || $u['role']==='admin') throw new HttpException(403,'FORBIDDEN','Não altere administradores por este recurso.');
        $level=Input::choice($d,'management_level',['none','company','coordination']); $active=self::flag($d,'active');
        if(($level==='coordination' && !in_array($u['role'],['doctor','nurse'],true)) || ($level==='company' && $u['role']!=='manager')) Input::fail('nível de gestão');
        return $this->store->transaction(function()use($id,$level,$active){$this->store->execute('UPDATE users SET management_level=?,active=? WHERE id=?',[$level,$active,$id]);$this->audit('account_updated','user',$id);return ['id'=>$id];});
    }
    public function team(array $d): array
    {
        $this->manage('accounts'); $name=Input::text($d,'name',150);
        $id=$this->store->insert('teams',['company_id'=>$this->company,'name'=>$name]);$this->audit('team_created','team',$id);return ['id'=>$id];
    }
    public function member(array $d): array
    {
        $this->manage('accounts'); $team=Input::id($d,'team_id'); $user=Input::id($d,'user_id');
        if(!$this->store->one('SELECT t.id FROM teams t JOIN users u ON u.company_id=t.company_id WHERE t.id=? AND u.id=? AND t.company_id=? AND u.role IN (?,?,?) AND u.active=1',[$team,$user,$this->company,'doctor','nurse','navigator'])) Input::fail('equipe/profissional');
        if(!$this->store->one('SELECT user_id FROM team_members WHERE team_id=? AND user_id=?',[$team,$user])) $this->store->insert('team_members',['team_id'=>$team,'user_id'=>$user]);
        $this->audit('team_member_assigned','user',$user);return ['team_id'=>$team,'user_id'=>$user];
    }
    public function assignment(array $d): array
    {
        $this->manage('care_operations');$team=Input::id($d,'team_id');$patient=Input::id($d,'patient_id');$active=self::flag($d,'active');
        if(!$this->store->one('SELECT t.id FROM teams t JOIN patients p ON p.company_id=t.company_id WHERE t.id=? AND p.id=? AND t.company_id=?',[$team,$patient,$this->company])) Input::fail('paciente/equipe');
        if(!$this->isPlatform() && !$this->store->one('SELECT user_id FROM team_members WHERE team_id=? AND user_id=?',[$team,$this->user['id']])) throw new HttpException(403,'FORBIDDEN','Coordenação limitada às próprias equipes.');
        if($active && ($d['authorization_verified']??false)!==true) Input::fail('autorização assistencial verificada');
        return $this->store->transaction(function()use($team,$patient,$active){
            $this->store->execute('UPDATE patients SET name=name WHERE id=?',[$patient]);
            $a=$this->store->oneLocked('SELECT id FROM care_team_assignments WHERE patient_id=? AND team_id=?',[$patient,$team]);
            if($a) {$id=(int)$a['id'];$this->store->execute('UPDATE care_team_assignments SET active=? WHERE id=?',[$active,$id]);}
            else $id=$this->store->insert('care_team_assignments',['company_id'=>$this->company,'patient_id'=>$patient,'team_id'=>$team,'active'=>$active]);
            $this->audit($active?'care_assignment_verified':'care_assignment_revoked','care_team_assignment',$id);return ['id'=>$id,'active'=>(bool)$active];
        });
    }
}
