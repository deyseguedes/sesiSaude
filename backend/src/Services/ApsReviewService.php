<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\{HttpException,Input};
use App\Repositories\Store;

/** Technical review never changes the source or marks a clinical event as done. */
final class ApsReviewService
{
 private const CODES=['CPF_INVALID','RETURN_MISSING','AGE_CACHE_DIFFERENCE','ORPHAN_OBSERVATION','INVALID_ROW'];
 public function __construct(private Store $s,private array $user){}
 private function authorize():void{(new ApsImportService($this->s,$this->user))->authorize();}
 public static function capture(Store $s,array $user,int $archiveId,array $report):void {
  $seen=[];
  foreach(['errors'=>'error','review'=>'review','warnings'=>'warning'] as $group=>$severity)foreach($report[$group] as $issue){
   $key=$issue['line'].'|'.($issue['code']??'INVALID_ROW').'|'.($issue['field']??'');if(isset($seen[$key]))continue;$seen[$key]=true;
   $s->insert('aps_import_reviews',['archive_id'=>$archiveId,'company_id'=>$user['company_id'],'visit_key'=>DemoWorkspace::key(),'source_line'=>$issue['line'],'code'=>$issue['code']??'INVALID_ROW','field'=>$issue['field']??'','severity'=>$severity,'message'=>$issue['message'],'status'=>'pending','version'=>1,'created_at'=>date('Y-m-d H:i:s')]);
  }
 }
 public function listing(array $filters):array {
  $this->authorize();$params=[$this->user['company_id'],DemoWorkspace::key()];$where='company_id=? AND visit_key=?';
  $summary=$this->s->all('SELECT status,code,COUNT(*) total FROM aps_import_reviews WHERE '.$where.' GROUP BY status,code',$params);
  $status=$filters['status']??'pending';if(!in_array($status,['pending','reviewed','all'],true))Input::fail('situação da revisão');if($status!=='all'){$where.=' AND status=?';$params[]=$status;}
  $code=$filters['code']??'';if($code!==''){if(!in_array($code,self::CODES,true))Input::fail('tipo de inconsistência');$where.=' AND code=?';$params[]=$code;}
  $page=filter_var($filters['page']??1,FILTER_VALIDATE_INT);if(!$page||$page<1||$page>100000)Input::fail('página');$offset=($page-1)*50;
  $total=(int)$this->s->one('SELECT COUNT(*) total FROM aps_import_reviews WHERE '.$where,$params)['total'];
  $items=$this->s->all('SELECT id,archive_id,source_line,code,field,severity,message,status,note,reviewed_at,version FROM aps_import_reviews WHERE '.$where.' ORDER BY id DESC LIMIT 50 OFFSET '.$offset,$params);
  foreach($items as &$item){$item['note']=$item['note']?Crypto::decrypt($item['note']):null;$item['version']=(int)$item['version'];}unset($item);
  return ['items'=>$items,'total'=>$total,'page'=>$page,'page_size'=>50,'summary'=>$summary];
 }
 public function detail(int $id):array {
  $this->authorize();$item=$this->find($id);$archive=$this->s->one('SELECT archive FROM aps_source_archives WHERE id=? AND company_id=? AND visit_key=?',[$item['archive_id'],$this->user['company_id'],DemoWorkspace::key()]);
  if(!$archive)throw new HttpException(404,'NOT_FOUND','Origem não encontrada.');
  $source=json_decode(Crypto::decrypt($archive['archive']),true,512,JSON_THROW_ON_ERROR);$index=array_search((int)$item['source_line'],array_map('intval',$source['source_lines']??[]),true);$values=[];
  if($index!==false){$headers=$source['headers']??[];$canonical=ApsFileReader::mapping($headers);
   foreach(ApsSource::cells($source['rows'][$index],$headers,$source['mapping']??[]) as $cell){
    if($cell['state']==='empty')continue;if($item['field']!==''&&$cell['target']!==$item['field'])continue;
    $value=(string)$cell['value'];$key=$canonical[$cell['column']]??$cell['target'];
    if($key==='cpf'||$cell['target']==='cpf')$value='***.***.***-'.substr(preg_replace('/\D/','',$value),-2);
    elseif($key==='phone'||$cell['target']==='phone')$value='(**) *****-'.substr(preg_replace('/\D/','',$value),-4);
    elseif(in_array($cell['target'],['birth_date','entry_date','last_consultation','return_date','plan_sent_at','last_nutrition','last_psychology','coordination_at','next_coordination_at'],true))try{$date=ApsImportService::date($value,$source['date_system']??'1900');$value=$date?(new \DateTimeImmutable($date))->format('d/m/Y'):'';}catch(\InvalidArgumentException){}
    $values[]=['header'=>$cell['header']??('Coluna '.($cell['column']+1)),'value'=>mb_substr($value,0,1000),'state'=>$cell['state']];
   }
  }
  $item['note']=$item['note']?Crypto::decrypt($item['note']):null;$item['version']=(int)$item['version'];
  return ['item'=>$item,'cells'=>$values,'sheet_name'=>$source['sheet']??null,'source_sha256'=>$source['original_sha256']??null];
 }
 private function find(int $id,bool $lock=false):array {
  $sql='SELECT * FROM aps_import_reviews WHERE id=? AND company_id=? AND visit_key=?';$params=[$id,$this->user['company_id'],DemoWorkspace::key()];
  $row=$lock?$this->s->oneLocked($sql,$params):$this->s->one($sql,$params);if(!$row)throw new HttpException(404,'NOT_FOUND','Revisão não encontrada.');return $row;
 }
 public function update(int $id,array $data):array {
  $this->authorize();$status=$data['status']??null;if(!in_array($status,['pending','reviewed'],true))Input::fail('situação');$note=Input::text($data,'note',1000);$version=filter_var($data['version']??null,FILTER_VALIDATE_INT);if(!$version||$version<1)Input::fail('versão');
  return $this->s->transaction(function()use($id,$status,$note,$version){$old=$this->find($id,true);if((int)$old['version']!==$version)throw new HttpException(409,'STALE_VERSION','Revisão alterada por outra pessoa. Atualize a lista.');
   $this->s->execute('UPDATE aps_import_reviews SET status=?,note=?,reviewer_id=?,reviewed_at=?,version=version+1 WHERE id=?',[$status,Crypto::encrypt($note),$this->user['id'],date('Y-m-d H:i:s'),$id]);
   Audit::record($this->s,$this->user,'aps_source_reviewed','company',(int)$this->user['company_id']);return ['id'=>$id,'status'=>$status,'version'=>$version+1];
  });
 }
}
