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
  $slots=$this->s->all("SELECT s.id,s.starts_at,s.ends_at,u.name unit FROM appointment_slots s JOIN service_units u ON u.id=s.service_unit_id WHERE s.company_id=? AND s.status='available' AND s.starts_at>? AND s.starts_at<=? AND s.starts_at LIKE ? ORDER BY s.starts_at LIMIT 3",[$this->user['company_id'],date('Y-m-d H:i:s'),$p['return_date'].' 23:59:59','% 14:30:00']);
  return array_intersect_key($p,array_flip(['registration','person_id','name','unit','scheduled_at','state','barrier','reply','replied_at']))+['slots'=>$slots,'campaign_id'=>6,'variant'=>'A','simulated'=>true];
 }
 public function reply(array $d):array{
  $this->guard(['worker','nurse','doctor','admin']);$choice=Input::choice($d,'choice',['confirm','reschedule','cannot','support']);$phone=$this->phone();$payload=['choice'=>$choice,'simulated'=>true];$now=date('Y-m-d H:i:s');
  if($choice==='cannot')$payload['barrier']=Input::choice($d,'barrier',['schedule','transport','attendance_prize','unnecessary','other']);
  if($choice==='reschedule'){$slot=Input::id($d,'slot_id');$found=null;foreach($phone['slots'] as $s)if((int)$s['id']===$slot)$found=$s;if(!$found)Input::fail('horário disponível e compatível com o turno');$payload+=['slot_id'=>$slot,'scheduled_at'=>$found['starts_at']];}
  $this->s->transaction(function()use($payload,$phone,$now){
   $this->s->insert('outreach_events',['company_id'=>$this->user['company_id'],'visit_key'=>DemoWorkspace::key(),'patient_id'=>$phone['person_id'],'campaign_id'=>6,'variant'=>'A','event_type'=>'reply','payload'=>Crypto::encrypt(json_encode($payload,JSON_THROW_ON_ERROR)),'created_at'=>$now]);
   Audit::record($this->s,$this->user,'outreach_reply','aps_person',$phone['person_id']);
  });
  return ['message'=>match($choice){'confirm'=>'Pronto! A enfermeira já vê sua confirmação.','reschedule'=>'Novo horário escolhido. Reagendamento pronto para enviar ao sistema da APS.','cannot'=>'Obrigado por contar. A enfermagem recebeu sua dificuldade.',default=>'Pedido de apoio recebido pela equipe.'},'phone'=>$this->phone()];
 }
 public function queue():array{
  $this->guard(['nurse','doctor','admin']);$now=new \DateTimeImmutable('today');$items=[];$waiting=[];
  foreach($this->people() as $p){if(($p['status']??'')!=='ATIVO'||!empty($p['replied_at'])&&in_array($p['state'],['confirmed','rescheduled'],true)||empty($p['return_date']))continue;
   $days=(int)$now->diff(new \DateTimeImmutable($p['return_date']))->format('%r%a');if($days>30)continue;
   $age=(int)(new \DateTimeImmutable(substr($p['message_sent_at']??date('Y-m-d'),0,10)))->diff($now)->format('%r%a');
   $p['days_due']=$days;$p['message_age']=$age;$p['why']=($days<0?'retorno vencido há '.abs($days).' dias':'retorno vence em '.$days.' dias').'; mensagem sem resposta há '.$age.' dias';
   if($p['reply']==='cannot')$p['why'].='; dificuldade voluntária: '.self::barrier($p['barrier']);
   if($age>=3||in_array($p['reply'],['cannot','support'],true))$items[]=$p;else $waiting[]=$p;
  }
  usort($items,fn($a,$b)=>$a['days_due']<=>$b['days_due']?:($b['reply']==='cannot')<=>($a['reply']==='cannot')?:$b['message_age']<=>$a['message_age']?:($b['risk_current']??0)<=>($a['risk_current']??0));
  return ['items'=>$items,'waiting'=>$waiting,'simulated'=>true];
 }
 public static function barrier(?string $key):string{return ['schedule'=>'Horário ou turno','transport'=>'Transporte','attendance_prize'=>'Receio de perder hora ou prêmio de assiduidade','unnecessary'=>'Não vê necessidade','other'=>'Outro'][$key??'']??'Sem motivo informado';}
 public function overlay(array $cases):array{
  if(!LocalProfileAccess::publicDemo())return $cases;$states=[];foreach($this->people() as $p)if(!empty($p['case_id'])&&$p['replied_at'])$states[$p['case_id']]=$p;
  foreach($cases as &$c)if(isset($states[$c['id']])){$p=$states[$c['id']];$c['status']=$p['state'];$c['appointment_status']=$p['state']==='pending'?'expected':($p['state']==='rescheduled'?'cancelled':'confirmed');$c['scheduled_at']=$p['scheduled_at'];$c['barrier']=$p['barrier'];$c['demo_scoped']=true;}unset($c);return $cases;
 }
}
