<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class OutreachService {
 public function __construct(public Store $s,public array $user){}
 public function guard(array $roles):void{if(!LocalProfileAccess::publicDemo())throw new HttpException(404,'NOT_FOUND','Cenário disponível na demonstração.');if(!in_array($this->user['role'],$roles,true))throw new HttpException(403,'FORBIDDEN','Perfil não autorizado.');}
 public function people():array{
  $rows=DemoWorkspace::people($this->s,(int)$this->user['company_id']);$events=$this->s->all('SELECT * FROM outreach_events WHERE company_id=? AND visit_key=? ORDER BY id',[$this->user['company_id'],DemoWorkspace::key()]);$states=[];
  foreach($events as $e){$e['data']=json_decode(Crypto::decrypt($e['payload']),true);if($e['event_type']==='reply')$states[$e['patient_id']]=$e;}
  $people=[];foreach($rows as $row){$p=$row['data'];$p['person_id']=(int)$row['id'];$p['patient_id']=$row['patient_id'];$p['state']='pending';$p['barrier']=null;$p['replied_at']=null;$p['reply']=null;
   if(isset($states[$row['id']])){$e=$states[$row['id']];$p['reply']=$e['data']['choice'];$p['state']=match($p['reply']){'confirm'=>'confirmed','reschedule'=>'rescheduled',default=>'pending'};$p['barrier']=$e['data']['barrier']??null;$p['replied_at']=$e['created_at'];if(isset($e['data']['scheduled_at']))$p['scheduled_at']=$e['data']['scheduled_at'];}
   $people[]=$p;
  }return $people;
 }
 public function phone():array{
  $this->guard(['worker','nurse','doctor','admin']);$p=null;foreach($this->people() as $row)if($row['registration']==='DEMO-0001'){$p=$row;break;}
  if(!$p)throw new HttpException(404,'NOT_FOUND','Marcos não está nesta base. Restaure o roteiro ou importe sua matrícula.');
  $slots=$this->s->all("SELECT s.id,s.starts_at,s.ends_at,u.name unit FROM appointment_slots s JOIN service_units u ON u.id=s.service_unit_id WHERE s.company_id=? AND s.status='available' AND s.starts_at>? AND s.starts_at<=? AND s.starts_at LIKE ? ORDER BY s.starts_at LIMIT 3",[$this->user['company_id'],DemoClock::now(),$p['return_date'].' 23:59:59','% 14:30:00']);
  return array_intersect_key($p,array_flip(['registration','person_id','name','unit','scheduled_at','state','barrier','reply','replied_at']))+['slots'=>$slots,'campaign_id'=>6,'variant'=>'A','simulated'=>true];
 }
 public function reply(array $d):array{
  $this->guard(['worker','nurse','doctor','admin']);$choice=Input::choice($d,'choice',['confirm','reschedule','cannot','support']);$phone=$this->phone();$payload=['choice'=>$choice,'simulated'=>true];$now=DemoClock::now();
  if($choice==='cannot')$payload['barrier']=Input::choice($d,'barrier',['schedule','transport','attendance_prize','unnecessary','other']);
  if($choice==='reschedule'){$slot=Input::id($d,'slot_id');$found=null;foreach($phone['slots'] as $s)if((int)$s['id']===$slot)$found=$s;if(!$found)Input::fail('horário disponível e compatível com o turno');$payload+=['slot_id'=>$slot,'scheduled_at'=>$found['starts_at']];}
  $this->s->transaction(function()use($payload,$phone,$now){
   $this->s->insert('outreach_events',['company_id'=>$this->user['company_id'],'visit_key'=>DemoWorkspace::key(),'patient_id'=>$phone['person_id'],'campaign_id'=>6,'variant'=>'A','event_type'=>'reply','payload'=>Crypto::encrypt(json_encode($payload,JSON_THROW_ON_ERROR)),'created_at'=>$now]);
   Audit::record($this->s,$this->user,'outreach_reply','aps_person',$phone['person_id']);
  });
  return ['message'=>match($choice){'confirm'=>'Pronto! A enfermeira já vê sua confirmação.','reschedule'=>'Novo horário escolhido. Reagendamento pronto para enviar ao sistema da APS.','cannot'=>'Obrigado por contar. A enfermagem recebeu sua dificuldade.',default=>'Pedido de apoio recebido pela equipe.'},'phone'=>$this->phone()];
 }
 public function queue():array{
  $this->guard(['nurse','doctor','admin']);$config=require dirname(__DIR__,2).'/config/outreach.php';$items=[];$waiting=[];
  $counts=[];foreach($this->s->all("SELECT patient_id,COUNT(*) n FROM care_pending_items WHERE company_id=? AND status<>'resolved' GROUP BY patient_id",[$this->user['company_id']]) as $r)$counts[$r['patient_id']]=(int)$r['n'];
  foreach($this->people() as $person){$p=OutreachRules::classify($person,DemoClock::today(),$config);if(!$p)continue;$p['pending_count']=$counts[$p['patient_id']??0]??0;$p['cpf']=empty($p['cpf'])?'':'***.***.***-'.substr($p['cpf'],-2);$p['phone']=empty($p['phone'])?'':'(**) *****-'.substr($p['phone'],-4);if($p['category']==='unanswered')$items[]=$p;else $waiting[]=$p;}
  usort($items,[OutreachRules::class,'compare']);return ['items'=>$items,'waiting'=>$waiting,'simulated'=>true];
 }
 public function export():array{
  $this->guard(['nurse','doctor','admin']);$rows=[['matrícula','nome','data','hora','situação','motivo','origem','gerado em']];
  foreach($this->people() as $p){$status=match($p['reply']){'confirm'=>'CONFIRMADO','reschedule'=>'REMARCADO','cannot'=>'NÃO PODE',default=>null};if(!$status)continue;$date=new \DateTimeImmutable($p['scheduled_at']);$rows[]=[$p['registration'],$p['name'],$date->format('d/m/Y'),$date->format('H:i'),$status,$p['barrier']?self::barrier($p['barrier']):'','VIVA+ ELO',date('d/m/Y H:i')];}
  $csv='';foreach($rows as $row){$cells=[];foreach($row as $cell){$cell=(string)$cell;if(preg_match('/^[=+@\\t\\r-]/',$cell))$cell="'".$cell;$cells[]='"'.str_replace('"','""',$cell).'"';}$csv.=implode(';',$cells)."\r\n";}
  Audit::record($this->s,$this->user,'outreach_exported','company',(int)$this->user['company_id']);return ['csv'=>$csv,'layout'=>'layout a ajustar ao sistema do SESI'];
 }
 public static function barrier(?string $key):string{return ['schedule'=>'Horário ou turno','transport'=>'Transporte','attendance_prize'=>'Receio de perder hora ou prêmio de assiduidade','unnecessary'=>'Não vê necessidade','other'=>'Outro'][$key??'']??'Sem motivo informado';}
 public function overlay(array $cases):array{
  if(!LocalProfileAccess::publicDemo())return $cases;$states=[];foreach($this->people() as $p)if(!empty($p['case_id'])&&$p['replied_at'])$states[$p['case_id']]=$p;
  foreach($cases as &$c)if(isset($states[$c['id']])){$p=$states[$c['id']];$c['status']=$p['state'];$c['appointment_status']=$p['state']==='pending'?'expected':($p['state']==='rescheduled'?'cancelled':'confirmed');$c['scheduled_at']=$p['scheduled_at'];$c['barrier']=$p['barrier'];$c['demo_scoped']=true;}unset($c);return $cases;
 }
}
