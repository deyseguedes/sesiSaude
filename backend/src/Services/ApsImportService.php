<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;
use DateTimeImmutable;
final class ApsImportService {
 public function __construct(private Store $s,private array $user){}
 public function authorize():void{if(!in_array($this->user['role'],['admin','nurse'],true))throw new HttpException(403,'FORBIDDEN','Somente SESI e enfermagem podem importar.');}
 public static function date(mixed $value,string $system='1900'):?string {
  if($value===null||trim((string)$value)==='')return null;$v=trim((string)$value);
  if(is_numeric($v)){if($system==='1904'){if((float)$v<0||(float)$v>71953)throw new \InvalidArgumentException('Data Excel inválida');return (new DateTimeImmutable('1904-01-01'))->modify('+'.(int)$v.' days')->format('Y-m-d');}if((float)$v<1||(float)$v>73415||(int)$v===60)throw new \InvalidArgumentException('Data Excel inválida');return (new DateTimeImmutable((int)$v<60?'1899-12-31':'1899-12-30'))->modify('+'.(int)$v.' days')->format('Y-m-d');}
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
 public function fileImport(array $d):array { $this->authorize();if(!isset($d['original_base64']))Input::fail('arquivo original obrigatório');return $this->import($d); }
 private static function extracted(array $d):array {
  if(!isset($d['original_base64']))return $d;
  if(!is_string($d['original_base64'])||strlen($d['original_base64'])>6990508)Input::fail('arquivo original');
  if(!is_string($d['filename']??null)||mb_strlen($d['filename'])>255)Input::fail('nome do arquivo');
  $bytes=base64_decode($d['original_base64'],true);if($bytes===false)Input::fail('arquivo original');
  try{$parsed=ApsFileReader::read($bytes,(string)($d['filename']??''));}catch(\InvalidArgumentException $error){throw new HttpException(422,'INVALID_SOURCE',$error->getMessage());}
  $mapping=$d['mapping']??ApsFileReader::mapping($parsed['headers']);if(!is_array($mapping)||count($mapping)>256)Input::fail('mapeamento');foreach($mapping as $column=>$key)if(!ctype_digit((string)$column)||(int)$column>255||!is_string($key))Input::fail('mapeamento');
  return array_replace($d,$parsed,['mapping'=>$mapping]);
 }
 public function inspectFile(array $d):array {
  $this->authorize();if(!isset($d['original_base64']))Input::fail('arquivo original obrigatório');$d=self::extracted($d);$d['preview']=true;
  $missing=array_diff(['registration','name','company','status'],array_values($d['mapping']));
  if($missing)return ['imported'=>0,'valid'=>0,'unchanged'=>0,'review'=>[],'warnings'=>[],'preview'=>[],'errors'=>[['line'=>1,'code'=>'MAPPING_REQUIRED','message'=>'Mapeie as colunas obrigatórias: '.implode(', ',$missing)]],'source'=>['headers'=>$d['headers'],'mapping'=>$d['mapping'],'rows'=>count($d['rows']),'sheet_name'=>$d['sheet_name'],'formula_count'=>count($d['formula_cells']),'server_extracted'=>true]];
  return $this->process($d);
 }
 public function import(array $d):array {$this->authorize();return $this->process(self::extracted($d));}
 private function process(array $d):array {
  $rows=$d['rows']??null;if(!is_array($rows)||!array_is_list($rows)||count($rows)>1000||!count($rows))Input::fail('até 1000 linhas');
  $mapping=$d['mapping']??[];if(!is_array($mapping)||count($mapping)>256)Input::fail('mapeamento');$targets=array_keys(json_decode(file_get_contents(dirname(__DIR__,2).'/config/aps-columns.json'),true,32,JSON_THROW_ON_ERROR));foreach($mapping as $column=>$key)if(!is_scalar($column)||!ctype_digit((string)$column)||(int)$column>255||!is_string($key)||($key!==''&&!in_array($key,$targets,true)))Input::fail('destino de coluna');$selected=array_values(array_filter($mapping,fn($v)=>$v!==''));if(count($selected)!==count(array_unique($selected)))Input::fail('coluna mapeada mais de uma vez');foreach(['registration','name','company','status'] as $required)if(!in_array($required,$mapping,true))Input::fail('coluna obrigatória: '.$required);
  $original=null;if(isset($d['original_base64'])){if(!is_string($d['original_base64'])||strlen($d['original_base64'])>6990508)Input::fail('arquivo original');$original=base64_decode($d['original_base64'],true);if($original===false||strlen($original)>5242880)Input::fail('arquivo original');}
  $preview=($d['preview']??true)!==false;$generate=($d['generate']??false)===true;
  if($generate&&!LocalProfileAccess::publicDemo())throw new HttpException(422,'DEMO_ONLY','Gerador disponível somente no banco fictício.');
  $report=['unchanged'=>0,'review'=>[],'imported'=>0,'valid'=>0,'errors'=>[],'warnings'=>[],'preview'=>[],'generated'=>$generate];$valid=[];$registrations=[];$cpfs=[];
  $allowed=['registration','name','birth_date','cpf','sex','entry_date','phone','company','doctor','status','last_consultation','return_date','demands','risk_initial','risk_current','multiprofessional','plan_sent_at','plan_status','coordination_dates','shift','care_line','contact_jul','contact_ago','contact_set','contact_out','contact_nov','contact_dez'];
  $allowed=array_merge($allowed,ApsSource::EXTRA_FIELDS);
  $report['source']=['headers'=>$d['headers']??[],'mapping'=>$mapping,'sheet_name'=>$d['sheet_name']??null,'rows'=>count($rows),'date_system'=>$d['date_system']??'1900','formula_count'=>count($d['formula_cells']??[]),'server_extracted'=>isset($d['extraction_version'])];
  foreach($rows as $i=>$source){$r=[];$line=(int)($d['source_lines'][$i]??($i+2));try{
   if(!is_array($source))throw new \InvalidArgumentException('Linha inválida');
   $cells=ApsSource::cells($source,$d['headers']??[],$mapping);
   foreach($mapping as $column=>$key)if(in_array($key,$allowed,true)){ $v=$source[$column]??'';if(!is_scalar($v)&&$v!==null)throw new \InvalidArgumentException('Célula inválida');$r[$key]=mb_substr(trim((string)$v),0,2000);}
   foreach($cells as $cell)if(in_array($cell['state'],['not_collected','not_applicable','not_exported'],true)&&$cell['target']!=='')unset($r[$cell['target']]);
   $r=ApsSource::normalize($r);
   if(empty($r['registration'])&&empty($r['name'])&&empty($r['company'])){$report['review'][]=['line'=>$line,'code'=>'ORPHAN_OBSERVATION','field'=>'','message'=>'Observação sem identificação; não vinculada a trabalhador.'];continue;}
   foreach(['registration','name','company','status'] as $key)if(empty($r[$key]))throw new \InvalidArgumentException('Campo obrigatório: '.$key);
   foreach(['birth_date','entry_date','last_consultation','return_date','plan_sent_at','last_nutrition','last_psychology','coordination_at','next_coordination_at'] as $key)if(!empty($r[$key]))$r[$key]=self::date($r[$key],($d['date_system']??'1900')==='1904'?'1904':'1900');
   if(!empty($r['birth_date'])&&$r['birth_date']>date('Y-m-d'))throw new \InvalidArgumentException('Nascimento no futuro');
   foreach(['risk_initial','risk_current'] as $key)if(isset($r[$key])&&$r[$key]!==''&&!in_array($r[$key],['1','2','3','4'],true))throw new \InvalidArgumentException('Risco fora de 1 a 4');
   foreach(['status'=>['ATIVO','INATIVO'],'sex'=>['M','F','OUTRO','NÃO INFORMADO'],'multiprofessional'=>['SIM','NÃO'],'plan_status'=>['ADERIU TOTALMENTE','ADERIU PARCIALMENTE','AGUARDANDO RESPOSTA','NÃO ADERIU']] as $key=>$choices)if(isset($r[$key])&&$r[$key]!==''&&!in_array(mb_strtoupper($r[$key]),$choices,true))throw new \InvalidArgumentException('Valor inválido: '.$key);else if(isset($r[$key])&&$r[$key]!=='')$r[$key]=mb_strtoupper($r[$key]);
   $cpf=preg_replace('/\D/','',$r['cpf']??'');if($cpf!==''&&strlen($cpf)!==11)throw new \InvalidArgumentException('CPF deve ter 11 dígitos');
   if($cpf!==''&&!ApsSource::cpfValid($cpf))$report['warnings'][]=['line'=>$line,'code'=>'CPF_INVALID','field'=>'cpf','message'=>'CPF com dígitos verificadores inválidos; preservado sem vínculo por CPF.'];
   if(isset($registrations[$r['registration']])||(ApsSource::cpfValid($cpf)&&isset($cpfs[$cpf])))throw new \InvalidArgumentException('Matrícula ou CPF duplicado no arquivo');
   if(ApsSource::cpfValid($cpf)&&$this->s->one('SELECT id FROM aps_people WHERE company_id=? AND visit_key=? AND cpf_hash=? AND registration<>?',[$this->user['company_id'],DemoWorkspace::key(),hash('sha256',$cpf),$r['registration']]))throw new \InvalidArgumentException('CPF já vinculado a outra matrícula');
   $registrations[$r['registration']]=true;if(ApsSource::cpfValid($cpf))$cpfs[$cpf]=true;$r['cpf']=$cpf;
   if($generate)$r=self::generate($r,$i,date('Y-m-d'));$r['dados_de_teste']=LocalProfileAccess::publicDemo();
   if(empty($r['return_date']))$report['warnings'][]=['line'=>$line,'code'=>'RETURN_MISSING','field'=>'return_date','message'=>'Sem previsão de retorno; cuidado não inferido.'];
   if(!empty($r['birth_date'])&&isset($r['age_source'])&&ctype_digit((string)$r['age_source'])&&(int)$r['age_source']!==(new DateTimeImmutable($r['birth_date']))->diff(new DateTimeImmutable(date('Y-m-d')))->y)$report['warnings'][]=['line'=>$line,'code'=>'AGE_CACHE_DIFFERENCE','field'=>'age_source','message'=>'Idade recebida difere do cálculo por aniversário na data da validação; origem preservada.'];
   $r['_source']=['version'=>1,'sheet'=>$d['sheet_name']??null,'line'=>$line,'date_system'=>$d['date_system']??'1900','fingerprint'=>hash('sha256',json_encode($cells,JSON_THROW_ON_ERROR))];
   $valid[]=['row'=>$r,'line'=>$line];$report['valid']++;
   if(count($report['preview'])<10){$masked=$r;unset($masked['_source']);$masked['cpf']=$cpf!==''?'***.***.***-'.substr($cpf,-2):'';$phone=preg_replace('/\D/','',$r['phone']??'');$masked['phone']=$phone?'(**) *****-'.substr($phone,-4):'';$report['preview'][]=$masked;}
  }catch(\InvalidArgumentException $e){$report['errors'][]=['line'=>$line,'code'=>'INVALID_ROW','field'=>'','message'=>$e->getMessage()];}}
  if(!$preview)$this->s->transaction(function()use($valid,&$report,$d,$original){
   $fingerprint=hash('sha256',json_encode(['version'=>2,'original_sha256'=>$original!==null?hash('sha256',$original):null,'rows'=>$d['rows'],'headers'=>$d['headers']??[],'mapping'=>$d['mapping'],'date_system'=>$d['date_system']??'1900','source_lines'=>$d['source_lines']??[],'generate'=>$report['generated']],JSON_THROW_ON_ERROR));
   $last=$this->s->one('SELECT id FROM aps_source_archives WHERE company_id=? AND visit_key=? AND fingerprint=?',[$this->user['company_id'],DemoWorkspace::key(),$fingerprint]);
   if($last){$report['unchanged']=count($valid);$report['reused']=true;return;}
   $report['source_fingerprint']=$fingerprint;$previousVersions=[];
   foreach($valid as $v){$r=$v['row'];$old=$this->s->one('SELECT id,payload FROM aps_people WHERE company_id=? AND visit_key=? AND registration=?',[$this->user['company_id'],DemoWorkspace::key(),$r['registration']]);if($old){$previousVersions[$r['registration']]=$old['payload'];$previous=json_decode(Crypto::decrypt($old['payload']),true,512,JSON_THROW_ON_ERROR);$r=ApsSource::merge($previous,$r);if($r==$previous){$report['unchanged']++;continue;}}
    $payload=Crypto::encrypt(json_encode($r,JSON_THROW_ON_ERROR));
    if($old)$this->s->execute('UPDATE aps_people SET payload=?,cpf_hash=?,test_data=?,updated_at=? WHERE id=?',[$payload,ApsSource::cpfValid($r['cpf']??'')?hash('sha256',$r['cpf']):null,(int)$r['dados_de_teste'],date('Y-m-d H:i:s'),$old['id']]);
    else $this->s->insert('aps_people',['company_id'=>$this->user['company_id'],'visit_key'=>DemoWorkspace::key(),'registration'=>$r['registration'],'cpf_hash'=>ApsSource::cpfValid($r['cpf']??'')?hash('sha256',$r['cpf']):null,'payload'=>$payload,'test_data'=>(int)$r['dados_de_teste'],'updated_at'=>date('Y-m-d H:i:s')]);$report['imported']++;
   }
   $archiveId=$this->s->insert('aps_source_archives',['company_id'=>$this->user['company_id'],'visit_key'=>DemoWorkspace::key(),'fingerprint'=>$fingerprint,'archive'=>Crypto::encrypt(json_encode(['version'=>2,'formula_cells'=>$d['formula_cells']??[],'extraction_version'=>$d['extraction_version']??null,'original_base64'=>$original!==null?base64_encode($original):null,'original_sha256'=>$original!==null?hash('sha256',$original):null,'headers'=>$d['headers']??[],'rows'=>$d['rows'],'source_lines'=>$d['source_lines']??[],'mapping'=>$d['mapping'],'sheet'=>$d['sheet_name']??null,'date_system'=>$d['date_system']??'1900','previous_versions'=>$previousVersions],JSON_THROW_ON_ERROR)),'created_at'=>date('Y-m-d H:i:s')]);
   ApsReviewService::capture($this->s,$this->user,$archiveId,$report);
   $this->s->insert('aps_imports',['company_id'=>$this->user['company_id'],'visit_key'=>DemoWorkspace::key(),'author_id'=>$this->user['id'],'filename'=>mb_substr(basename((string)($d['filename']??'exportacao')),0,190),'report'=>Crypto::encrypt(json_encode($report,JSON_THROW_ON_ERROR)),'created_at'=>date('Y-m-d H:i:s')]);
   Audit::record($this->s,$this->user,'aps_imported','company',(int)$this->user['company_id']);
  });
  return $report;
 }
 private static function publicReport(string $encrypted):array{$report=json_decode(Crypto::decrypt($encrypted),true,512,JSON_THROW_ON_ERROR);unset($report['_source_archive']);return $report;}
 public function last():array{$this->authorize();$r=$this->s->one('SELECT * FROM aps_imports WHERE company_id=? AND visit_key=? ORDER BY id DESC LIMIT 1',[$this->user['company_id'],DemoWorkspace::key()]);return $r?['visit_id'=>DemoWorkspace::key(),'created_at'=>$r['created_at'],'filename'=>$r['filename'],'report'=>self::publicReport($r['report'])]:['created_at'=>null];}
}
