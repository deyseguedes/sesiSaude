<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Input;
final class SuccessDashboard {
 public function __construct(private OutreachService $outreach){}
 public function read(array $filter=[]):array {
  $this->outreach->guard(['admin','manager']);$user=$this->outreach->user;$from=isset($filter['from'])?Input::date($filter,'from'):date('Y-m-01');$to=isset($filter['to'])?Input::date($filter,'to'):date('Y-m-d',strtotime('+30 days'));if($to<$from)Input::fail('período');
  $all=$this->outreach->people();$companies=array_values(array_unique(array_column($all,'company')));$company=$filter['company']??'';
  if(!is_string($company))Input::fail('empresa');
  if($company!=='')$all=array_values(array_filter($all,fn($p)=>$p['company']===$company));
  if($user['role']==='manager'){$own=$this->outreach->s->one('SELECT name FROM companies WHERE id=?',[$user['company_id']])['name'];$all=array_values(array_filter($all,fn($p)=>$p['company']===$own));}
  $all=array_values(array_filter($all,fn($p)=>($p['status']??'')==='ATIVO'));$group=count($all);
  if($user['role']==='manager'&&$group<10)return ['kind'=>'company','suppressed'=>true,'message'=>'grupo pequeno demais para exibir','minimum_group'=>10];
  $due=array_values(array_filter($all,fn($p)=>!empty($p['return_date'])&&$p['return_date']>=$from&&$p['return_date']<=$to));$n=count($due);if($user['role']==='manager'&&$n<10)return ['kind'=>'company','suppressed'=>true,'message'=>'grupo pequeno demais para exibir','minimum_group'=>10];$sent=0;$replied=0;$scheduled=0;$attended=0;$ontime=0;$adhered=0;$checkins=0;$riskEvaluated=0;$riskMaintained=0;$eligible=0;$barriers=[];
  foreach($due as $p){$sent+=(int)!empty($p['message_sent_at']);$replied+=(int)!empty($p['replied_at']);$scheduled+=(int)in_array($p['state'],['confirmed','rescheduled'],true);$attended+=(int)!empty($p['attended_at']);$ontime+=(int)(!empty($p['attended_at'])&&substr($p['attended_at'],0,10)<=$p['return_date']);
   $adhered+=(int)in_array($p['plan_status']??'',['ADERIU TOTALMENTE','ADERIU PARCIALMENTE'],true);$checkins+=(int)(($p['checkins_answered']??0)>0);
   if(isset($p['risk_initial'],$p['risk_current'])){$riskEvaluated++;$riskMaintained+=(int)($p['risk_current']<=$p['risk_initial']);}
   if($p['barrier'])$barriers[$p['barrier']]=($barriers[$p['barrier']]??0)+1;
  }
  foreach($all as $p){$participated=($p['semester_attended']??false)||(($p['rescheduled_notice_hours']??0)>=24);if($participated&&($p['semester_return_on_time']??false)&&($p['checkins_answered']??0)>=3)$eligible++;}
  $rate=fn($a,$b)=>$b?100*$a/$b:null;
  $common=['simulated'=>true,'from'=>$from,'to'=>$to,'population'=>$group,'minimum_group'=>10,'eligible'=>$eligible>=10?$eligible:null,'eligibility_suppressed'=>$eligible<10,'eligibility_criterion'=>'Compareceu ou remarcou com 24h; retorno no prazo do risco; respondeu 3 de 4 check-ins. Não consegui conta. Fora do critério: resultado de saúde, número de consultas e status de adesão.'];
  if($user['role']==='manager')return $common+['kind'=>'company','suppressed'=>false,'participation'=>['due'=>$n,'responded'=>$replied,'confirmed_or_rescheduled'=>$scheduled,'attended'=>$attended,'on_time_percent'=>$rate($ontime,$n)],'campaigns'=>[['name'=>'Convocação de retorno, novembro/2026','invited'=>$n,'responded'=>$replied]],'message'=>'Somente agregados da própria empresa. Sem risco, plano, motivos de saúde ou nomes.'];
  $barrierRows=[];foreach($barriers as $key=>$count)$barrierRows[]=['name'=>OutreachService::barrier($key),'count'=>$count];
  return $common+['kind'=>'sesi','companies'=>$companies,'funnel'=>[['label'=>'Retornos devidos','value'=>$n],['label'=>'Mensagens enviadas','value'=>$sent],['label'=>'Responderam','value'=>$replied],['label'=>'Confirmaram ou remarcaram','value'=>$scheduled],['label'=>'Compareceram','value'=>$attended]],'levels'=>[['title'=>'Participação','value'=>$rate($attended,$scheduled),'text'=>'Compareceram ÷ confirmados ou remarcados. Linha de base de cerca de 50% é premissa do desafio, a validar no piloto. Retorno no prazo: '.($ontime).' de '.$n.'.'],['title'=>'Adesão ao plano','value'=>$rate($adhered,$n),'text'=>'Aderiu totalmente ou parcialmente ÷ devidos. Check-in respondido por '.$checkins.' de '.$n.' pessoas.'],['title'=>'Resultado de risco','value'=>$rate($riskMaintained,$riskEvaluated),'text'=>'Manteve ou melhorou ÷ pessoas com risco inicial e atual. Dados sintéticos; não demonstra efeito causal da solução.']],'operation'=>['contacts_avoided'=>$replied,'hours_saved'=>$replied*5/60,'formula'=>'Estimativa: 1 resposta evita 1 contato manual; 5 minutos por contato ÷ 60. Não é economia comprovada.'],'barriers'=>$barrierRows,'recognition'=>['name'=>'Equipe Norte','source'=>'Exemplo ilustrativo de apuração; histórico simulado, independente das 100 pessoas.','result'=>TeamRecognitionService::calculate(['due_count'=>510,'excluded_count'=>0,'on_time_count'=>280,'baseline_percent'=>46,'satisfaction_percent'=>94,'period'=>'quarter','frozen_verified'=>true,'baseline_verified'=>true,'no_upheld_pressure'=>true,'risk_audit_passed'=>true,'consent_compliant'=>true])]];
 }
}
