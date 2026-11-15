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
  $status=$start<=date('Y-m-d')?'active':'planned';$payload['frozen']=$status==='active'?$this->freeze($payload['criteria']):null;$payload['frozen_at']=$status==='active'?date('Y-m-d H:i:s'):null;
  $version=1;if($parent){$old=$this->row($parent);$version=(int)$old['version']+1;}
  $id=$this->outreach->s->insert('campaign_experiments',['company_id'=>$this->outreach->user['company_id'],'visit_key'=>DemoWorkspace::key(),'parent_id'=>$parent,'version'=>$version,'name'=>$name,'kind'=>$kind,'status'=>$status,'payload'=>Crypto::encrypt(json_encode($payload,JSON_THROW_ON_ERROR)),'created_at'=>date('Y-m-d H:i:s')]);Audit::record($this->outreach->s,$this->outreach->user,'experiment_created','campaign_experiment',$id);return ['id'=>$id,'version'=>$version,'frozen_count'=>count($payload['frozen']??[])];
 }
 private function freeze(array $criteria):array{
  $frozen=[];foreach($this->outreach->people() as $p){$matches=true;foreach($criteria as $key=>$value)if($value!==''&&(string)($p[$key==='risk'?'risk_current':$key]??'')!==$value)$matches=false;if($matches)$frozen[]=['person_id'=>$p['person_id'],'registration'=>$p['registration'],'variant'=>self::variant($p['registration'])];}return $frozen;
 }
 public function row(int $id):array{
  $row=$this->outreach->s->one('SELECT * FROM campaign_experiments WHERE id=? AND company_id=? AND visit_key IN (?,?)',[$id,$this->outreach->user['company_id'],DemoWorkspace::key(),'template']);if(!$row)throw new HttpException(404,'NOT_FOUND','Campanha não encontrada.');$row['data']=json_decode(Crypto::decrypt($row['payload']),true,512,JSON_THROW_ON_ERROR);return $row;
 }
 public function listing():array{
  $this->outreach->guard(['admin','nurse','manager']);$rows=$this->outreach->s->all('SELECT id FROM campaign_experiments WHERE company_id=? AND visit_key IN (?,?) ORDER BY id',[$this->outreach->user['company_id'],DemoWorkspace::key(),'template']);$result=[];foreach($rows as $r)$result[]=$this->detail((int)$r['id']);return $result;
 }
 public function detail(int $id):array{
  $this->outreach->guard(['admin','nurse','manager']);$r=$this->row($id);$d=$r['data'];$n=count($d['frozen']??[]);
  if($this->outreach->user['role']==='manager')return ['id'=>(int)$r['id'],'name'=>$r['name'],'suppressed'=>$n<10,'participation'=>$n>=10?['invited'=>$n]:null,'simulated'=>true];
  unset($d['frozen']);return ['id'=>(int)$r['id'],'name'=>$r['name'],'kind'=>$r['kind'],'version'=>(int)$r['version'],'parent_id'=>$r['parent_id'],'status'=>$r['status'],'population'=>$n,'data'=>$d,'simulated'=>true];
 }
 public function start(int $id):array{
  $this->manage();$r=$this->row($id);if($r['visit_key']!==DemoWorkspace::key())throw new HttpException(403,'FORBIDDEN','Duplique o exemplo antes de alterar.');
  $d=$r['data'];if($d['start_date']>date('Y-m-d'))Input::fail('campanha ainda não começou');if($d['frozen']!==null)return $this->detail($id);$d['frozen']=$this->freeze($d['criteria']);$d['frozen_at']=date('Y-m-d H:i:s');$this->outreach->s->execute('UPDATE campaign_experiments SET payload=?,status=? WHERE id=?',[Crypto::encrypt(json_encode($d,JSON_THROW_ON_ERROR)),'active',$id]);return $this->detail($id);
 }
}
