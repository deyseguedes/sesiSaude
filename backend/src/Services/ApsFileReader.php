<?php
declare(strict_types=1);
namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/** Bounded, read-only extraction. No formulas, links or macros are executed. */
final class ApsFileReader
{
 private const MAX_XML=8388608;
 private static function xml(string $value):DOMXPath {
  if(!class_exists(DOMDocument::class))throw new InvalidArgumentException('O servidor precisa da extensão PHP dom para ler XLSX.');
  if(preg_match('/<!DOCTYPE|<!ENTITY/i',$value))throw new InvalidArgumentException('XML com entidades ou DTD não é aceito.');
  $previous=libxml_use_internal_errors(true);
  try{$doc=new DOMDocument();if(!$doc->loadXML($value,LIBXML_NONET|LIBXML_COMPACT))throw new InvalidArgumentException('XML inválido no XLSX.');if($doc->doctype!==null)throw new InvalidArgumentException('XML com DTD não é aceito.');return new DOMXPath($doc);}
  finally{libxml_clear_errors();libxml_use_internal_errors($previous);}
 }
 private static function text(DOMXPath $xml,DOMElement $node):string {
  $value='';foreach($xml->query('./*[local-name()="t"]|./*[local-name()="r"]/*[local-name()="t"]',$node) as $part)$value.=$part->textContent;return $value;
 }
 private static function column(string $reference):int {
  if(!preg_match('/^([A-Z]{1,3})[1-9][0-9]*$/D',$reference,$m))throw new InvalidArgumentException('Referência de célula inválida.');
  $index=0;foreach(str_split($m[1]) as $letter)$index=$index*26+ord($letter)-64;
  if($index>256)throw new InvalidArgumentException('Limite de 256 colunas.');return $index-1;
 }
 private static function populated(array $row):bool {foreach($row as $value)if(trim((string)$value)!=='')return true;return false;}
 public static function read(string $bytes,string $filename):array {
  if($bytes===''||strlen($bytes)>5242880)throw new InvalidArgumentException('Arquivo vazio ou maior que 5 MB.');
  return match(strtolower(pathinfo($filename,PATHINFO_EXTENSION))){'csv'=>self::csv($bytes),'xlsx'=>self::xlsx($bytes),default=>throw new InvalidArgumentException('Use arquivo CSV ou XLSX.')};
 }
 private static function result(array $rows,array $lines,array $meta):array {
  if(count($rows)<2)throw new InvalidArgumentException('Use cabeçalho e pelo menos uma linha preenchida.');
  $headers=array_map(fn($v)=>(string)($v??''),array_shift($rows));array_shift($lines);
  return ['headers'=>$headers,'rows'=>$rows,'source_lines'=>$lines]+$meta;
 }
 private static function csv(string $bytes):array {
  $bytes=preg_replace('/^\xEF\xBB\xBF/','',$bytes);
  if(!mb_check_encoding($bytes,'UTF-8'))throw new InvalidArgumentException('Salve o CSV em UTF-8.');
  $quoted=false;for($i=0,$length=strlen($bytes);$i<$length;$i++)if($bytes[$i]==='"'){if($quoted&&$i+1<$length&&$bytes[$i+1]==='"')$i++;else $quoted=!$quoted;}
  if($quoted)throw new InvalidArgumentException('CSV com aspas não fechadas.');
  $first=preg_split('/\r\n|\r|\n/',$bytes,2)[0];$delimiter=substr_count($first,';')>substr_count($first,',')?';':',';
  $stream=fopen('php://temp','w+b');fwrite($stream,$bytes);rewind($stream);$rows=[];$lines=[];$line=1;
  try{while(!feof($stream)){$start=ftell($stream);$row=fgetcsv($stream,0,$delimiter,'"','');$end=ftell($stream);if($row===false)break;$consumed=substr($bytes,$start,$end-$start);$current=$line;$line+=preg_match_all('/\r\n|\r|\n/',$consumed);if(count($row)>256)throw new InvalidArgumentException('Limite de 256 colunas.');if(!self::populated($row))continue;$rows[]=$row;$lines[]=$current;if(count($rows)>1001)throw new InvalidArgumentException('Limite de 1000 linhas preenchidas.');}}
  finally{fclose($stream);}
  return self::result($rows,$lines,['date_system'=>'1900','sheet_name'=>null,'formula_cells'=>[],'extraction_version'=>2]);
 }
 private static function xlsx(string $bytes):array {
  if(!class_exists(ZipArchive::class))throw new InvalidArgumentException('O servidor precisa da extensão PHP zip para ler XLSX.');
  $path=tempnam(sys_get_temp_dir(),'aps-');if($path===false)throw new \RuntimeException('Temporary file unavailable');
  $zip=new ZipArchive();$opened=false;
  try{
   if(file_put_contents($path,$bytes)!==strlen($bytes)||$zip->open($path)!==true)throw new InvalidArgumentException('Arquivo XLSX inválido.');$opened=true;
   if($zip->numFiles>256)throw new InvalidArgumentException('XLSX excede o limite de componentes.');
   $names=[];$total=0;for($i=0;$i<$zip->numFiles;$i++){$entry=$zip->statIndex($i);$name=$entry['name'];if(isset($names[$name]))throw new InvalidArgumentException('Componente duplicado no XLSX.');$names[$name]=true;$total+=$entry['size'];if($entry['size']>self::MAX_XML||$total>33554432)throw new InvalidArgumentException('XLSX descompactado excede o limite.');}
   $read=function(string $name,bool $required=true)use($zip):string{$value=$zip->getFromName($name);if($value===false){if(!$required)return '';throw new InvalidArgumentException('Componente obrigatório ausente no XLSX.');}return $value;};
   $workbook=self::xml($read('xl/workbook.xml'));$relationships=self::xml($read('xl/_rels/workbook.xml.rels'));
   $sheet=$workbook->query('/*[local-name()="workbook"]/*[local-name()="sheets"]/*[local-name()="sheet"]')->item(0);
   if(!$sheet instanceof DOMElement)throw new InvalidArgumentException('Nenhuma aba encontrada.');
   $id=$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id');$target=null;
   foreach($relationships->query('/*/*[local-name()="Relationship"]') as $relation)if($relation->getAttribute('Id')===$id){if($relation->getAttribute('TargetMode')==='External')throw new InvalidArgumentException('Aba externa não é aceita.');$target=$relation->getAttribute('Target');break;}
   if(!$target||str_contains($target,'..')||str_contains($target,'\\')||str_contains($target,':'))throw new InvalidArgumentException('Caminho da aba inválido.');
   $target=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.preg_replace('#^\./#','',$target);
   $shared=[];$sharedSource=$read('xl/sharedStrings.xml',false);
   if($sharedSource!==''){$sx=self::xml($sharedSource);foreach($sx->query('/*/*[local-name()="si"]') as $item)$shared[]=self::text($sx,$item);}
   $xml=self::xml($read($target));$rows=[];$lines=[];$formulas=[];$seenLines=[];
   foreach($xml->query('/*/*[local-name()="sheetData"]/*[local-name()="row"]') as $rowNode){
    $line=filter_var($rowNode->getAttribute('r'),FILTER_VALIDATE_INT);if(!$line||$line<1||$line>1048576||isset($seenLines[$line]))throw new InvalidArgumentException('Número de linha inválido ou duplicado.');$seenLines[$line]=true;$row=[];
    foreach($xml->query('./*[local-name()="c"]',$rowNode) as $cell){$reference=$cell->getAttribute('r');$column=self::column($reference);if((int)preg_replace('/^[A-Z]+/','',$reference)!==$line||array_key_exists($column,$row))throw new InvalidArgumentException('Referência de célula conflitante.');
     $type=$cell->getAttribute('t');$value=$xml->evaluate('string(./*[local-name()="v"])',$cell);
     if($type==='s'){if(!ctype_digit($value)||!array_key_exists((int)$value,$shared))throw new InvalidArgumentException('Texto compartilhado inválido.');$value=$shared[(int)$value];}
     elseif($type==='inlineStr'){$inline=$xml->query('./*[local-name()="is"]',$cell)->item(0);$value=$inline instanceof DOMElement?self::text($xml,$inline):'';}
     $row[$column]=$value;
     $formula=$xml->query('./*[local-name()="f"]',$cell)->item(0);if($formula instanceof DOMElement)$formulas[]=['cell'=>$reference,'line'=>$line,'column'=>$column,'formula'=>$formula->textContent,'shared_index'=>$formula->getAttribute('si'),'cached'=>$value];
    }
    if(!self::populated($row))continue;$width=$row?max(array_keys($row))+1:0;$row=array_replace(array_fill(0,$width,null),$row);$rows[]=array_values($row);$lines[]=$line;
    if(count($rows)>1001)throw new InvalidArgumentException('Limite de 1000 linhas preenchidas.');
   }
   $properties=$workbook->query('/*/*[local-name()="workbookPr"]')->item(0);$system=$properties instanceof DOMElement&&in_array($properties->getAttribute('date1904'),['1','true'],true)?'1904':'1900';
   return self::result($rows,$lines,['date_system'=>$system,'sheet_name'=>$sheet->getAttribute('name'),'formula_cells'=>$formulas,'extraction_version'=>2]);
  }finally{if($opened)$zip->close();if(is_file($path))unlink($path);}
 }
 public static function mapping(array $headers):array {
  $aliases=json_decode(file_get_contents(dirname(__DIR__,2).'/config/aps-columns.json'),true,32,JSON_THROW_ON_ERROR);$lookup=[];
  $normalize=static fn(string $value)=>preg_replace('/\s+/u',' ',trim(mb_strtolower(strtr($value,['Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ü'=>'U','Ç'=>'C','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ü'=>'u','ç'=>'c']))));
  foreach($aliases as $key=>$labels)foreach(array_merge([$key],$labels) as $label)$lookup[$normalize($label)]=$key;
  return array_map(fn($header)=>$lookup[$normalize((string)$header)]??'',$headers);
 }
}
