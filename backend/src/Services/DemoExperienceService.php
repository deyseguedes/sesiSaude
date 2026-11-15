<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
final class DemoExperienceService {
 public function __construct(private Store $store,private array $user) {}
 private function guard(array $roles): void {
  if(!LocalProfileAccess::publicDemo()) throw new HttpException(404,'NOT_FOUND','Cenário demonstrativo indisponível.');
  if(!in_array($this->user['role'],$roles,true)) throw new HttpException(403,'FORBIDDEN','Perfil não autorizado.');
 }
 public function channel(): array {
  $this->guard(['worker','admin','nurse','doctor','navigator']);
  $rows=$this->store->all("SELECT p.id,p.name,a.id appointment_id,a.scheduled_at,a.status FROM patients p JOIN appointments a ON a.patient_id=p.id WHERE p.company_id=? AND p.name IN (?,?) AND a.scheduled_at>=? AND a.status IN ('expected','confirmed') ORDER BY p.id,a.scheduled_at",[$this->user['company_id'],'Carla Silva [Teste 001]','José Santos [Teste 002]',date('Y-m-d').' 00:00:00']);
  $people=[];foreach($rows as $row) if(!isset($people[$row['id']])) {
   $row['display_name']=str_starts_with($row['name'],'Carla')?'Carla':'Seu José';
   $row['responses']=$this->store->all("SELECT e.event_type,e.created_at,e.payload FROM care_pending_events e JOIN care_pending_items i ON i.id=e.item_id WHERE i.patient_id=? AND e.event_type='worker_reply' ORDER BY e.id DESC LIMIT 5",[$row['id']]);
   foreach($row['responses'] as &$r) $r['payload']=json_decode(Crypto::decrypt($r['payload']),true);unset($r);$people[$row['id']]=$row;
  }
  return ['simulated'=>true,'people'=>array_values($people)];
 }
 public function reply(array $d): array {
  $this->guard(['worker','admin','nurse','doctor','navigator']);
  $consent=$d['leader_consent']??false;if(!is_bool($consent))Input::fail('leader_consent');
  $patient=Input::id($d,'patient_id');$choice=Input::choice($d,'choice',['confirm','reschedule','help','audio_help','call']);
  $allowed=array_column($this->channel()['people'],null,'id');
  if(!isset($allowed[$patient])) throw new HttpException(404,'NOT_FOUND','Personagem não encontrado.');
  $person=$allowed[$patient];$now=date('Y-m-d H:i:s');
  return $this->store->transaction(function()use($person,$patient,$choice,$now,$consent){
   $item=$this->store->oneLocked("SELECT * FROM care_pending_items WHERE patient_id=? AND source_type='manual' AND kind='worker_channel' LIMIT 1",[$patient]);
   $team=$this->store->one('SELECT team_id FROM care_team_assignments WHERE patient_id=? AND active=1 LIMIT 1',[$patient]);
   $a=$this->store->one('SELECT * FROM appointments WHERE id=?',[$person['appointment_id']]);
   $labels=['confirm'=>'Retorno confirmado por botão','reschedule'=>'Solicitação de novo horário','help'=>'Pedido de apoio por botão','audio_help'=>'Pedido de apoio por áudio simulado','call'=>'Solicitação de ligação'];
   $help=$choice!=='confirm';
   $v=['title'=>$labels[$choice],'reason'=>'Resposta recebida no canal simulado do trabalhador.','next_action'=>$help?'Enfermagem: entrar em contato e pactuar o próximo passo.':'Programação confirmada; aguardar atendimento verificado.','help_requested'=>(int)$help,'status'=>$help?'open':'in_progress','updated_at'=>$now];
   if(!$item) $id=$this->store->insert('care_pending_items',$v+['company_id'=>$this->user['company_id'],'patient_id'=>$patient,'source_type'=>'manual','source_id'=>$patient,'kind'=>'worker_channel','team_id'=>$team['team_id']??null,'professional_id'=>$a['professional_id'],'service_unit_id'=>$a['service_unit_id'],'due_at'=>$now,'created_at'=>$now]);
   else {$id=(int)$item['id'];$keys=array_keys($v);$this->store->execute('UPDATE care_pending_items SET '.implode(',',array_map(fn($k)=>$k.'=?',$keys)).',version=version+1 WHERE id=?',[...array_values($v),$id]);}
   if($choice==='confirm') {
    $this->store->execute("UPDATE appointments SET status='confirmed' WHERE id=? AND status='expected'",[$person['appointment_id']]);
    $this->store->execute("UPDATE continuity_cases SET status='confirmed',version=version+1,updated_at=? WHERE appointment_id=? AND status='pending'",[$now,$person['appointment_id']]);
   }
   $this->store->insert('care_pending_events',['item_id'=>$id,'author_id'=>$this->user['id'],'event_type'=>'worker_reply','note'=>Crypto::encrypt($labels[$choice].' — cenário fictício.'),'payload'=>Crypto::encrypt(json_encode(['leader_consent'=>$consent,'choice'=>$choice,'channel'=>$choice==='audio_help'?'simulated_audio':'simulated_buttons','simulated'=>true],JSON_THROW_ON_ERROR)),'created_at'=>$now]);
   Audit::record($this->store,$this->user,'demo_worker_reply','care_pending_item',$id);
   return ['item_id'=>$id,'message'=>$labels[$choice].'. Fila da enfermagem atualizada.','simulated'=>true];
  });
 }
 public function leader(): array {
  $this->guard(['leader','admin','manager']);
  $rows=$this->store->all("SELECT p.id,p.name,e.payload,a.scheduled_at FROM patients p JOIN care_pending_items i ON i.patient_id=p.id JOIN care_pending_events e ON e.item_id=i.id JOIN appointments a ON a.patient_id=p.id WHERE p.company_id=? AND e.event_type='worker_reply' AND a.scheduled_at>=? AND a.status IN ('expected','confirmed') ORDER BY e.id DESC,a.scheduled_at",[$this->user['company_id'],date('Y-m-d').' 00:00:00']);
  $seen=[];$authorized=[];foreach($rows as $r){if(isset($seen[$r['id']]))continue;$seen[$r['id']]=true;$payload=json_decode(Crypto::decrypt($r['payload']),true);if(($payload['leader_consent']??false)!==true)continue;$authorized[]=['name'=>$r['name'],'scheduled_at'=>$r['scheduled_at']];}
  $count=(int)$this->store->one('SELECT COUNT(*) n FROM patients WHERE company_id=?',[$this->user['company_id']])['n'];
  return ['simulated'=>true,'authorized'=>$authorized,'participation'=>$count>=10?$count:null,'minimum_group'=>10];
 }
 public function management(): array {
  $this->guard(['admin']);$c=$this->user['company_id'];
  $n=(int)$this->store->one('SELECT COUNT(*) n FROM patients WHERE company_id=?',[$c])['n'];
  $scheduled=(int)$this->store->one('SELECT COUNT(DISTINCT patient_id) n FROM appointments WHERE company_id=?',[$c])['n'];
  $attended=(int)$this->store->one("SELECT COUNT(DISTINCT patient_id) n FROM appointments WHERE company_id=? AND status='attended'",[$c])['n'];
  $due=(int)$this->store->one('SELECT COUNT(*) n FROM clinical_return_plans WHERE company_id=?',[$c])['n'];
  $dueNow=(int)$this->store->one('SELECT COUNT(*) n FROM clinical_return_plans WHERE company_id=? AND due_date<=?',[$c,date('Y-m-d')])['n'];
  $onTime=(int)$this->store->one("SELECT COUNT(*) n FROM clinical_return_plans r WHERE r.company_id=? AND r.due_date<=? AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id=r.patient_id AND a.company_id=r.company_id AND a.status='attended' AND a.scheduled_at>CONCAT(r.assessment_date,' 23:59:59') AND a.scheduled_at<=CONCAT(r.due_date,' 23:59:59') AND a.scheduled_at<=?)",[$c,date('Y-m-d'),date('Y-m-d H:i:s')])['n'];
  $continued=(int)$this->store->one("SELECT COUNT(*) n FROM clinical_return_plans r WHERE r.company_id=? AND EXISTS (SELECT 1 FROM appointments a WHERE a.patient_id=r.patient_id AND a.company_id=r.company_id AND a.status='attended')",[$c])['n'];
  $risk=$this->store->all('SELECT risk_cipher FROM clinical_return_plans WHERE company_id=?',[$c]);$high=0;
  foreach($risk as $r) if((int)Crypto::decrypt($r['risk_cipher'])>=3)$high++;
  $scenario=['due_count'=>510,'excluded_count'=>0,'on_time_count'=>280,'baseline_percent'=>46.0,'satisfaction_percent'=>94.0,'period'=>'quarter','frozen_verified'=>true,'baseline_verified'=>true,'no_upheld_pressure'=>true,'risk_audit_passed'=>true,'consent_compliant'=>true];
  Audit::record($this->store,$this->user,'demo_management_read','company',(int)$c);
  return ['simulated'=>true,'funnel'=>[['label'=>'Pessoas na base','value'=>$n],['label'=>'Com agendamento','value'=>$scheduled],['label'=>'Com comparecimento','value'=>$attended],['label'=>'Compareceu e tem retorno pactuado','value'=>$continued]],'levels'=>[['title'=>'Participação','value'=>$n,'text'=>'Pessoas distintas cadastradas; não equivale a adesão.'],['title'=>'Adesão ao cuidado','value'=>$onTime,'text'=>"Retorno no prazo entre $dueNow pessoas com retorno devido até hoje, desde a avaliação. Fotografia sintética; carteira ainda não congelada."],['title'=>'Resultado de risco','value'=>null,'text'=>"Sem segunda avaliação comparável: resultado ainda não apurado. A fotografia atual tem $high pessoas com risco 3 ou 4; isso não demonstra melhora."]],'recognition'=>['name'=>'Equipe Norte','source'=>'Exemplo agregado independente da base de 100 pessoas; não é apuração real.','inputs'=>$scenario,'result'=>TeamRecognitionService::calculate($scenario)]];
 }
}
