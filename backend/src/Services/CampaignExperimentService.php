<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
final class CampaignExperimentService {
 public function __construct(private OutreachService $outreach){}
 private function manage():void{$this->outreach->guard(['admin','nurse']);}
 public static function variant(string $registration):string{return hexdec(substr(hash('sha256','sesi:'.$registration),0,2))%2?'A':'B';}
 public function create(array $d,?int $parent=null):array{
  $this->manage();$name=Input::text($d,'name',190);$kind=Input::choice($d,'kind',['return_call','reminder','orientation','incentive','offline']);$main=Input::choice($d,'main_metric',['response','on_time','attendance','checkin','adherence']);$comparison=Input::choice($d,'comparison',['ab','stepped','historical']);
  $start=Input::date($d,'start_date');$end=Input::date($d,'end_date');if($end<$start)Input::fail('fim do período');
  $payload=['objective'=>Input::text($d,'objective',1000),'cycle'=>Input::text($d,'cycle',100),'unit'=>Input::text($d,'unit',150,true),'contents'=>Input::text($d,'contents',1000,true),'channel'=>Input::choice($d,'channel',['whatsapp','phone','onsite','dds','poster']),'text_a'=>Input::text($d,'text_a',2000),'text_b'=>Input::text($d,'text_b',2000,true),'send_time'=>Input::text($d,'send_time',5),'start_date'=>$start,'end_date'=>$end,'measurement_from'=>Input::date($d,'measurement_from'),'measurement_to'=>Input::date($d,'measurement_to'),'main_metric'=>$main,'comparison'=>$comparison,'criteria'=>[],'simulated'=>true];
  if(!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$payload['send_time']))Input::fail('horário');
  if($payload['measurement_to']<$payload['measurement_from'])Input::fail('janela de medição');
  foreach(['status','risk','company','shift','care_line'] as $key)$payload['criteria'][$key]=Input::text($d,'filter_'.$key,150,true);
  if($payload['criteria']['status']!==''&&!in_array($payload['criteria']['status'],['ATIVO','INATIVO'],true))Input::fail('status');
  if($payload['criteria']['risk']!==''&&!in_array($payload['criteria']['risk'],['1','2','3','4'],true))Input::fail('risco');
  foreach(['minimum_gain_pp'=>4,'max_cost'=>126,'message_cost'=>.05,'hour_cost'=>25,'benefit_cost'=>0] as $key=>$default){$v=$d[$key]??$default;if(!is_numeric($v)||!is_finite((float)$v)||(float)$v<0||(float)$v>10000000)Input::fail($key);$payload[$key]=(float)$v;}
  if($payload['minimum_gain_pp']<=0||$payload['minimum_gain_pp']>100)Input::fail('meta');
  $status=$start<=DemoClock::today()?'active':'planned';$payload['frozen']=$status==='active'?$this->freeze($payload['criteria']):null;$payload['frozen_at']=$status==='active'?DemoClock::now():null;
  $version=1;if($parent){$old=$this->row($parent);$version=(int)$old['version']+1;}
  $id=$this->outreach->s->insert('campaign_experiments',['company_id'=>$this->outreach->user['company_id'],'visit_key'=>DemoWorkspace::key(),'parent_id'=>$parent,'version'=>$version,'name'=>$name,'kind'=>$kind,'status'=>$status,'payload'=>Crypto::encrypt(json_encode($payload,JSON_THROW_ON_ERROR)),'created_at'=>DemoClock::now()]);Audit::record($this->outreach->s,$this->outreach->user,'experiment_created','campaign_experiment',$id);return ['id'=>$id,'version'=>$version,'frozen_count'=>count($payload['frozen']??[])];
 }
 private function freeze(array $criteria):array{
  $frozen=[];foreach($this->outreach->people() as $p){$matches=true;foreach($criteria as $key=>$value)if($value!==''&&(string)($p[$key==='risk'?'risk_current':$key]??'')!==$value)$matches=false;if($matches)$frozen[]=['person_id'=>$p['person_id'],'registration'=>$p['registration'],'variant'=>self::variant($p['registration'])];}return $frozen;
 }
 public function row(int $id):array{
  $row=$this->outreach->s->one('SELECT * FROM campaign_experiments WHERE id=? AND company_id=? AND visit_key IN (?,?)',[$id,$this->outreach->user['company_id'],DemoWorkspace::key(),'template']);if(!$row)throw new HttpException(404,'NOT_FOUND','Campanha não encontrada.');$row['data']=json_decode(Crypto::decrypt($row['payload']),true,512,JSON_THROW_ON_ERROR);
  if($id===6&&$row['visit_key']==='template'){$key=DemoWorkspace::key();if(!isset($_SESSION['live_frozen'][$key]))$_SESSION['live_frozen'][$key]=$this->freeze(['status'=>'ATIVO']);$row['data']['frozen']=$_SESSION['live_frozen'][$key];}return $row;
 }
 public function listing():array{
  $this->outreach->guard(['admin','nurse','manager']);$rows=$this->outreach->s->all('SELECT id FROM campaign_experiments WHERE company_id=? AND visit_key IN (?,?) ORDER BY id',[$this->outreach->user['company_id'],DemoWorkspace::key(),'template']);$result=[];foreach($rows as $r){if($this->outreach->user['role']==='manager'&&isset($this->row((int)$r['id'])['data']['history']))continue;$result[]=$this->detail((int)$r['id']);}return $result;
 }
 public function detail(int $id):array{
  $this->outreach->guard(['admin','nurse','manager']);$r=$this->row($id);$d=$r['data'];if($this->outreach->user['role']==='manager'){$own=$this->outreach->s->one('SELECT name FROM companies WHERE id=?',[$this->outreach->user['company_id']])['name'];$ids=array_column(array_filter($this->outreach->people(),fn($p)=>$p['company']===$own),'person_id');$d['frozen']=array_values(array_filter($d['frozen']??[],fn($p)=>in_array($p['person_id'],$ids,true)));}
  $n=isset($d['history'])?(int)$d['history']['n1']+(int)$d['history']['n2']:count($d['frozen']??[]);
  if($this->outreach->user['role']==='manager'&&isset($d['history']))throw new HttpException(404,'NOT_FOUND','Histórico de teste restrito ao SESI.');
  if($this->outreach->user['role']==='manager')return ['id'=>(int)$r['id'],'name'=>$r['name'],'suppressed'=>$n<10,'participation'=>$n>=10?['invited'=>$n]:null,'simulated'=>true];
  $decisions=$this->outreach->s->all('SELECT d.decision,d.justification,d.created_at,u.name author FROM campaign_decisions d JOIN users u ON u.id=d.author_id WHERE d.campaign_id=? AND d.visit_key=? ORDER BY d.id DESC',[$id,DemoWorkspace::key()]);foreach($decisions as &$decision)$decision['justification']=Crypto::decrypt($decision['justification']);unset($decision);
  $stats=$this->statistics($r);$diagnosis=$this->diagnosis($r,$stats);unset($d['frozen']);return ['id'=>(int)$r['id'],'name'=>$r['name'],'kind'=>$r['kind'],'version'=>(int)$r['version'],'parent_id'=>$r['parent_id'],'status'=>$r['status'],'population'=>$n,'data'=>$d,'decisions'=>$decisions,'suppressed'=>$n<10,'statistics'=>$n>=10?$stats:null,'diagnosis'=>$n>=10?$diagnosis:null,'decision'=>CampaignDecision::suggest($stats),'simulated'=>true];
 }
 public function recordDecision(int $id,array $d):array{
  $this->manage();$this->row($id);$decision=Input::choice($d,'decision',['maintain','adjust','redesign','end','pause','wait']);$justification=Input::text($d,'justification',1500);
  $this->outreach->s->insert('campaign_decisions',['campaign_id'=>$id,'visit_key'=>DemoWorkspace::key(),'author_id'=>$this->outreach->user['id'],'decision'=>$decision,'justification'=>Crypto::encrypt($justification),'created_at'=>DemoClock::now()]);Audit::record($this->outreach->s,$this->outreach->user,'campaign_decision','campaign_experiment',$id);return $this->detail($id);
 }
 public function export():array{
  $this->manage();$rows=[['campanha','tipo','indicador','efeito_pp','limite_inferior','limite_superior','custo_por_retorno','sugestão']];
  foreach($this->listing() as $c){$s=$c['statistics'];$num=fn($v)=>$v===null?'não apurável':number_format($v,2,',','.');$rows[]=[$c['name'],$c['kind'],$c['data']['main_metric'],$num($s['effect_pp']??null),$num($s['lower_pp']??null),$num($s['upper_pp']??null),$s&&$s['cost_per_recovered']!==null?$num($s['cost_per_recovered']):'sem ganho',$c['decision']['label']];}
  $csv='';foreach($rows as $row){$cells=[];foreach($row as $v){if(preg_match('/^[=+@\\t\\r-]/',(string)$v))$v="'".$v;$cells[]='"'.str_replace('"','""',(string)$v).'"';}$csv.=implode(';',$cells)."\r\n";}return ['csv'=>$csv];
 }
 public function diagnosis(array $row,array $stats):array {
  $d=$row['data'];$funnels=$d['funnels']??$stats['funnels']??['A'=>['sent'=>$stats['sent'],'read'=>$stats['sent'],'responded'=>$stats['responded_total'],'scheduled'=>$stats['scheduled'],'attended'=>$stats['attended']]];
  if($row['kind']==='orientation'&&!isset($d['funnels']))$funnels=['A'=>['invited'=>$stats['n1'],'interested'=>$stats['responded72'],'welcomed'=>$stats['scheduled'],'participating'=>$stats['scheduled'],'completed'=>$stats['attended']]];
  return CampaignDiagnosis::read($stats,$funnels,$d['segments']??$stats['segments']??[],$row['kind'],$d['barriers']??[]);
 }
 public function statistics(array $row):array{
  $d=$row['data'];if(isset($d['history']))return CampaignStatistics::calculate($d['history']+['minimum_gain_pp'=>$d['minimum_gain_pp']??4,'max_cost'=>$d['max_cost']??126,'period_ended'=>true]);
  $people=$this->outreach->people();$frozen=$d['frozen']??[];$membership=array_column($frozen,'variant','person_id');$source=[];foreach($people as $p)if(isset($membership[$p['person_id']])){$p['variant']=$membership[$p['person_id']];$source[]=$p;}
  $metric=$d['main_metric']??'on_time';$a=[];$b=[];$hours=[];$sent=0;$responded=0;$scheduled=0;$attended=0;$onTime=0;$checkins=0;$adhered=0;$respondedTotal=0;$arms=['A'=>['sent'=>0,'read'=>0,'responded'=>0,'scheduled'=>0,'attended'=>0],'B'=>['sent'=>0,'read'=>0,'responded'=>0,'scheduled'=>0,'attended'=>0]];$groups=[];
  $events=$this->outreach->s->all('SELECT patient_id,payload,created_at FROM outreach_events WHERE company_id=? AND visit_key=? AND campaign_id=? AND event_type=? ORDER BY id',[$this->outreach->user['company_id'],DemoWorkspace::key(),$row['id'],'reply']);$replies=[];foreach($events as $event)$replies[$event['patient_id']]=$event;
  foreach($source as $p){$reply=$replies[$p['person_id']]??null;$choice=$reply?json_decode(Crypto::decrypt($reply['payload']),true)['choice']:null;$responseHours=$reply?max(0,(strtotime($reply['created_at'])-strtotime($p['message_sent_at']))/3600):null;
   $isA=$p['variant']==='A';$success=match($metric){'response'=>$responseHours!==null&&$responseHours<=72,'attendance'=>!empty($p['attended_at']),'checkin'=>($p['checkins_answered']??0)>0,'adherence'=>in_array($p['plan_status']??'',['ADERIU TOTALMENTE','ADERIU PARCIALMENTE'],true),default=>!empty($p['attended_at'])&&substr($p['attended_at'],0,10)<=$p['return_date']};
   $arm=$p['variant'];$isSent=($p['campaign_id']??null)==$row['id']&&!empty($p['message_sent_at'])&&substr($p['message_sent_at'],0,10)>=$d['measurement_from']&&substr($p['message_sent_at'],0,10)<=$d['measurement_to'];
   $arms[$arm]['sent']+=(int)$isSent;$arms[$arm]['read']+=(int)$isSent;$arms[$arm]['responded']+=(int)($reply!==null);$arms[$arm]['scheduled']+=(int)in_array($choice,['confirm','reschedule'],true);$arms[$arm]['attended']+=(int)!empty($p['attended_at']);
   if($isA){$age=!empty($p['birth_date'])?(new \DateTimeImmutable($p['birth_date']))->diff(new \DateTimeImmutable(DemoClock::today()))->y:null;$dimensions=['Empresa: '.($p['company']??''),'Turno: '.($p['shift']??'não informado'),'Canal: '.($d['channel']??'não informado')];if($age!==null)$dimensions[]='Faixa etária: '.($age<40?'até 39':'40 ou mais');foreach($dimensions as $dimension){$groups[$dimension]??=['n'=>0,'success'=>0];$groups[$dimension]['n']++;$groups[$dimension]['success']+=(int)$success;}}
   if($isA)$a[]=(int)$success;else $b[]=(int)$success;
   if($isA){$respondedTotal+=(int)($reply!==null);$sent+=(int)$isSent;$responded+=(int)($responseHours!==null&&$responseHours<=72);if($responseHours!==null)$hours[]=$responseHours;$scheduled+=(int)in_array($choice,['confirm','reschedule'],true);$attended+=(int)!empty($p['attended_at']);$onTime+=(int)(!empty($p['attended_at'])&&substr($p['attended_at'],0,10)<=$p['return_date']);$checkins+=(int)(($p['checkins_answered']??0)>0);$adhered+=(int)in_array($p['plan_status']??'',['ADERIU TOTALMENTE','ADERIU PARCIALMENTE'],true);}
  }
  $stats=CampaignStatistics::calculate(['n1'=>count(array_filter($membership,fn($v)=>$v==='A')),'n2'=>count(array_filter($membership,fn($v)=>$v==='B')),'x1'=>array_sum($a),'x2'=>array_sum($b),'sent'=>$sent,'responded_total'=>$respondedTotal,'responded72'=>$responded,'response_hours'=>$hours,'scheduled'=>$scheduled,'attended'=>$attended,'on_time'=>$onTime,'checkins'=>$checkins,'adhered'=>$adhered,'minimum_gain_pp'=>$d['minimum_gain_pp']??4,'max_cost'=>$d['max_cost']??126,'message_cost'=>$d['message_cost']??.05,'period_ended'=>($d['measurement_to']??'9999')<DemoClock::today()]);
  if(($d['comparison']??'ab')!=='ab'){$stats['n2']=0;$stats['effect_pp']=null;$stats['lower_pp']=null;$stats['upper_pp']=null;$stats['cost_per_recovered']=null;$stats['recovered']=null;$stats['comparison_pending']=true;}
  $segments=[];foreach($groups as $name=>$group)if($group['n']>=10)$segments[]=['name'=>$name,'n'=>$group['n'],'rate'=>100*$group['success']/$group['n']];return $stats+['funnels'=>$arms,'segments'=>$segments];
 }
 public function start(int $id):array{
  $this->manage();$r=$this->row($id);if($r['visit_key']!==DemoWorkspace::key())throw new HttpException(403,'FORBIDDEN','Duplique o exemplo antes de alterar.');
  $d=$r['data'];if($d['start_date']>DemoClock::today())Input::fail('campanha ainda não começou');if($d['frozen']!==null)return $this->detail($id);$d['frozen']=$this->freeze($d['criteria']);$d['frozen_at']=DemoClock::now();$this->outreach->s->execute('UPDATE campaign_experiments SET payload=?,status=? WHERE id=?',[Crypto::encrypt(json_encode($d,JSON_THROW_ON_ERROR)),'active',$id]);return $this->detail($id);
 }
}
