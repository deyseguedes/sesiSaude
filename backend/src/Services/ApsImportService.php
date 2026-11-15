<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
use DateTimeImmutable;
final class ApsImportService {
 public function __construct(private Store $s,private array $user){}
 public function authorize():void{if(!in_array($this->user['role'],['admin','nurse'],true))throw new HttpException(403,'FORBIDDEN','Somente SESI e enfermagem podem importar.');}
 public static function date(mixed $value):?string {
  if($value===null||trim((string)$value)==='')return null;$v=trim((string)$value);
  if(is_numeric($v)){if((float)$v<1||(float)$v>73415||(int)$v===60)throw new \InvalidArgumentException('Data Excel inválida');return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int)$v.' days')->format('Y-m-d');}
  $format=str_contains($v,'/')?'!d/m/Y':'!Y-m-d';$d=DateTimeImmutable::createFromFormat($format,$v);$errors=DateTimeImmutable::getLastErrors();
  if(!$d||($errors&&($errors['warning_count']||$errors['error_count'])))throw new \InvalidArgumentException('Data inválida');
  return $d->format('Y-m-d');
 }
 public static function generate(array $r,int $index,string $today):array {
  $n=abs(crc32((string)$r['registration']));$position=$index%100;$risk=$position<30?1:($position<65?2:($position<90?3:4));
  $set=function($k,$v)use(&$r){if(!isset($r[$k])||$r[$k]==='')$r[$k]=$v;};
  $set('risk_current',$risk);$risk=(int)$r['risk_current'];$set('risk_initial',max(1,min(4,$risk+([-1,0,1][$n%3]))));
  $d=new DateTimeImmutable($today);$last=$index<20?$d->modify('-7 months'):$d->modify('-1 month')->modify('+1 day');
  $set('last_consultation',$last->format('Y-m-d'));$months=$risk<=2?6:($risk===3?3:2);
  $due=(new DateTimeImmutable($r['last_consultation']))->modify('+'.$months.' months');
  $set('return_date',$due->format('Y-m-d'));$set('return_defined_by',$risk===4?'definido pelo médico':'protocolo SESI');
  $set('plan_status',['ADERIU TOTALMENTE','ADERIU PARCIALMENTE','AGUARDANDO RESPOSTA','NÃO ADERIU'][$n%4]);
  $set('multiprofessional',$n%2?'SIM':'NÃO');$set('plan_sent_at',$r['last_consultation']);
  $set('demands',['Compatibilizar turno e retorno','Apoio no transporte','Orientação acessível','Revisar plano pactuado'][$n%4]);
  $set('checkins_answered',$n%5);$set('checkins_total',4);
  $set('message_sent_at',$d->modify('-'.($n%7+1).' days')->format('Y-m-d').' 10:00:00');
  foreach(['jul','ago','set','out','nov','dez'] as $i=>$m)$set('contact_'.$m,$r['plan_status']==='AGUARDANDO RESPOSTA'?'SEM RESPOSTA':($i<4?'CONTATO REALIZADO':'PREVISTO'));
  $r['dados_de_teste']=true;return $r;
 }
 public function import(array $d):array {
  $this->authorize();$rows=$d['rows']??null;if(!is_array($rows)||!array_is_list($rows)||count($rows)>1000||!count($rows))Input::fail('até 1000 linhas');
  $mapping=$d['mapping']??[];foreach(['registration','name','company','status'] as $required)if(!in_array($required,$mapping,true))Input::fail('coluna obrigatória: '.$required);
  $preview=($d['preview']??true)!==false;$generate=($d['generate']??false)===true;
  if($generate&&!LocalProfileAccess::publicDemo())throw new HttpException(422,'DEMO_ONLY','Gerador disponível somente no banco fictício.');
  $report=['imported'=>0,'valid'=>0,'errors'=>[],'warnings'=>[],'preview'=>[],'generated'=>$generate];$valid=[];$registrations=[];$cpfs=[];
  $allowed=['registration','name','birth_date','cpf','sex','entry_date','phone','company','doctor','status','last_consultation','return_date','demands','risk_initial','risk_current','multiprofessional','plan_sent_at','plan_status','coordination_dates','shift','care_line','contact_jul','contact_ago','contact_set','contact_out','contact_nov','contact_dez'];
  foreach($rows as $i=>$source){$r=[];try{
   if(!is_array($source))throw new \InvalidArgumentException('Linha inválida');
   foreach($mapping as $column=>$key)if(in_array($key,$allowed,true)){ $v=$source[$column]??'';if(!is_scalar($v)&&$v!==null)throw new \InvalidArgumentException('Célula inválida');$r[$key]=mb_substr(trim((string)$v),0,2000);}
   foreach(['registration','name','company','status'] as $key)if(empty($r[$key]))throw new \InvalidArgumentException('Campo obrigatório: '.$key);
   foreach(['birth_date','entry_date','last_consultation','return_date','plan_sent_at'] as $key)if(!empty($r[$key]))$r[$key]=self::date($r[$key]);
   if(!empty($r['birth_date'])&&$r['birth_date']>date('Y-m-d'))throw new \InvalidArgumentException('Nascimento no futuro');
   foreach(['risk_initial','risk_current'] as $key)if(!empty($r[$key])&&!in_array($r[$key],['1','2','3','4'],true))throw new \InvalidArgumentException('Risco fora de 1 a 4');
   foreach(['status'=>['ATIVO','INATIVO'],'sex'=>['M','F','OUTRO','NÃO INFORMADO'],'multiprofessional'=>['SIM','NÃO'],'plan_status'=>['ADERIU TOTALMENTE','ADERIU PARCIALMENTE','AGUARDANDO RESPOSTA','NÃO ADERIU']] as $key=>$choices)if(!empty($r[$key])&&!in_array(mb_strtoupper($r[$key]),$choices,true))throw new \InvalidArgumentException('Valor inválido: '.$key);else if(!empty($r[$key]))$r[$key]=mb_strtoupper($r[$key]);
   $cpf=preg_replace('/\D/','',$r['cpf']??'');if($cpf!==''&&strlen($cpf)!==11)throw new \InvalidArgumentException('CPF deve ter 11 dígitos');
   if(isset($registrations[$r['registration']])||($cpf!==''&&isset($cpfs[$cpf])))throw new \InvalidArgumentException('Matrícula ou CPF duplicado no arquivo');
   if($cpf!==''&&$this->s->one('SELECT id FROM aps_people WHERE company_id=? AND visit_key=? AND cpf_hash=? AND registration<>?',[$this->user['company_id'],DemoWorkspace::key(),hash('sha256',$cpf),$r['registration']]))throw new \InvalidArgumentException('CPF já vinculado a outra matrícula');
   $registrations[$r['registration']]=true;if($cpf!=='')$cpfs[$cpf]=true;$r['cpf']=$cpf;
   if($generate)$r=self::generate($r,$i,date('Y-m-d'));$r['dados_de_teste']=LocalProfileAccess::publicDemo();
   if(empty($r['return_date']))$report['warnings'][]=['line'=>$i+2,'message'=>'Sem previsão de retorno; cuidado não inferido.'];
   $valid[]=['row'=>$r,'line'=>$i+2];$report['valid']++;
   if(count($report['preview'])<10){$masked=$r;$masked['cpf']=$cpf!==''?'***.***.***-'.substr($cpf,-2):'';$phone=preg_replace('/\D/','',$r['phone']??'');$masked['phone']=$phone?'(**) *****-'.substr($phone,-4):'';$report['preview'][]=$masked;}
  }catch(\InvalidArgumentException $e){$report['errors'][]=['line'=>$i+2,'message'=>$e->getMessage()];}}
  if(!$preview)$this->s->transaction(function()use($valid,&$report,$d){
   foreach($valid as $v){$r=$v['row'];$old=$this->s->one('SELECT id FROM aps_people WHERE company_id=? AND visit_key=? AND registration=?',[$this->user['company_id'],DemoWorkspace::key(),$r['registration']]);$payload=Crypto::encrypt(json_encode($r,JSON_THROW_ON_ERROR));
    if($old)$this->s->execute('UPDATE aps_people SET payload=?,cpf_hash=?,test_data=?,updated_at=? WHERE id=?',[$payload,$r['cpf']?hash('sha256',$r['cpf']):null,(int)$r['dados_de_teste'],date('Y-m-d H:i:s'),$old['id']]);
    else $this->s->insert('aps_people',['company_id'=>$this->user['company_id'],'visit_key'=>DemoWorkspace::key(),'registration'=>$r['registration'],'cpf_hash'=>$r['cpf']?hash('sha256',$r['cpf']):null,'payload'=>$payload,'test_data'=>(int)$r['dados_de_teste'],'updated_at'=>date('Y-m-d H:i:s')]);$report['imported']++;
   }
   $this->s->insert('aps_imports',['company_id'=>$this->user['company_id'],'visit_key'=>DemoWorkspace::key(),'author_id'=>$this->user['id'],'filename'=>mb_substr(basename((string)($d['filename']??'exportacao')),0,190),'report'=>Crypto::encrypt(json_encode($report,JSON_THROW_ON_ERROR)),'created_at'=>date('Y-m-d H:i:s')]);
   Audit::record($this->s,$this->user,'aps_imported','company',(int)$this->user['company_id']);
  });
  return $report;
 }
 public function last():array{$this->authorize();$r=$this->s->one('SELECT * FROM aps_imports WHERE company_id=? AND visit_key=? ORDER BY id DESC LIMIT 1',[$this->user['company_id'],DemoWorkspace::key()]);return $r?['created_at'=>$r['created_at'],'filename'=>$r['filename'],'report'=>json_decode(Crypto::decrypt($r['report']),true)]:['created_at'=>null];}
}
