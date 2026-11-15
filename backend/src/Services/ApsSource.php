<?php
declare(strict_types=1);
namespace App\Services;

/** Source interpretation only: never creates clinical facts. */
final class ApsSource
{
 public const EXTRA_FIELDS=['age_source','last_nutrition','last_psychology','contact_history','risk_review_source','goals_review_source','coordination_at','next_coordination_at'];
 public static function cpfValid(string $cpf):bool {
  if(!preg_match('/^\d{11}$/',$cpf)||preg_match('/^(\d)\1{10}$/',$cpf))return false;
  for($length=9;$length<=10;$length++){$sum=0;for($i=0;$i<$length;$i++)$sum+=(int)$cpf[$i]*($length+1-$i);$digit=($sum*10)%11;if($digit===10)$digit=0;if($digit!==(int)$cpf[$length])return false;}return true;
 }
 public static function normalize(array $row):array {
  if(isset($row['sex']))$row['sex']=match(mb_strtoupper($row['sex'])){'FEMININO'=>'F','MASCULINO'=>'M',default=>$row['sex']};
  foreach(['risk_initial','risk_current'] as $key)if(isset($row[$key])&&preg_match('/^RISCO\s+([1-4])$/iu',$row[$key],$match))$row[$key]=$match[1];
  return $row;
 }
 public static function cells(array $source,array $headers,array $mapping):array {
  $cells=[];$width=max(count($headers),$source?max(array_map('intval',array_keys($source)))+1:0);
  for($i=0;$i<$width;$i++){
   $value=$source[$i]??null;if(!is_scalar($value)&&$value!==null)throw new \InvalidArgumentException('Célula inválida');
   $text=trim((string)$value);
   $state=match(mb_strtoupper($text)){'NÃO COLETADO'=>'not_collected','NÃO APLICÁVEL'=>'not_applicable','NÃO INFORMADO NA EXPORTAÇÃO'=>'not_exported',default=>$text===''?'empty':'recorded'};
   $cells[]=['column'=>$i,'header'=>$headers[$i]??null,'target'=>$mapping[$i]??'','value'=>$value,'state'=>$state];
  }
  return $cells;
 }
 /** Empty or absent source values never erase previously recorded care. */
 public static function merge(array $previous,array $incoming):array {
  foreach($incoming as $key=>$value)if($value!==null&&$value!=='')$previous[$key]=$value;
  return $previous;
 }
}
