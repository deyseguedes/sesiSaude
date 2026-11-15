<?php
declare(strict_types=1);
namespace App\Services;

/** Bounded ZIP reader for hosts without ext-zip. Never extracts filesystem paths. */
final class ApsZipReader
{
 public int $numFiles=0;
 private string $bytes='';
 private array $entries=[];
 private function number(int $offset,int $length):int {
  if($offset<0||$offset+$length>strlen($this->bytes))throw new \InvalidArgumentException('Diretório XLSX inválido.');
  return unpack($length===2?'v':'V',substr($this->bytes,$offset,$length))[1];
 }
 public function open(string $path):bool {
  $this->bytes=file_get_contents($path);$length=strlen($this->bytes);$end=null;
  for($i=$length-22;$i>=max(0,$length-65557);$i--)if(substr($this->bytes,$i,4)==="PK\x05\x06"&&$i+22+$this->number($i+20,2)===$length){$end=$i;break;}
  if($end===null||$this->number($end+4,2)!==0||$this->number($end+6,2)!==0)throw new \InvalidArgumentException('Arquivo XLSX inválido.');
  $this->numFiles=$this->number($end+10,2);if($this->numFiles>256||$this->number($end+8,2)!==$this->numFiles)throw new \InvalidArgumentException('XLSX excede o limite de componentes.');
  $offset=$this->number($end+16,4);$directoryEnd=$offset+$this->number($end+12,4);if($directoryEnd>$end)throw new \InvalidArgumentException('Diretório XLSX inválido.');
  for($i=0;$i<$this->numFiles;$i++){
   if(substr($this->bytes,$offset,4)!=="PK\x01\x02"||$offset+46>$directoryEnd)throw new \InvalidArgumentException('Diretório XLSX inválido.');
   $nameLength=$this->number($offset+28,2);$next=$offset+46+$nameLength+$this->number($offset+30,2)+$this->number($offset+32,2);if($next>$directoryEnd||($this->number($offset+8,2)&1)||$this->number($offset+34,2)!==0)throw new \InvalidArgumentException('Componente XLSX inválido ou cifrado.');
   $this->entries[]=['name'=>substr($this->bytes,$offset+46,$nameLength),'size'=>$this->number($offset+24,4),'compressed'=>$this->number($offset+20,4),'method'=>$this->number($offset+10,2),'crc'=>$this->number($offset+16,4),'local'=>$this->number($offset+42,4)];$offset=$next;
  }
  if($offset!==$directoryEnd)throw new \InvalidArgumentException('Diretório XLSX inconsistente.');return true;
 }
 public function statIndex(int $index):array {return $this->entries[$index];}
 public function getFromName(string $name):string|false {
  foreach($this->entries as $entry)if($entry['name']===$name){
   $offset=$entry['local'];if(substr($this->bytes,$offset,4)!=="PK\x03\x04"||($this->number($offset+6,2)&1)||$this->number($offset+8,2)!==$entry['method'])throw new \InvalidArgumentException('Componente XLSX inválido.');
   $start=$offset+30+$this->number($offset+26,2)+$this->number($offset+28,2);
   if($entry['size']>8388608||$start+$entry['compressed']>strlen($this->bytes))throw new \InvalidArgumentException('Componente XLSX excede o limite.');
   $compressed=substr($this->bytes,$start,$entry['compressed']);
   $value=match($entry['method']){0=>$compressed,8=>@gzinflate($compressed,8388608),default=>throw new \InvalidArgumentException('Compressão XLSX não suportada.')};
   if($value===false||strlen($value)!==$entry['size']||sprintf('%u',crc32($value))!==sprintf('%u',$entry['crc']))throw new \InvalidArgumentException('Componente XLSX corrompido.');return $value;
  }
  return false;
 }
 public function close():bool {$this->bytes='';$this->entries=[];return true;}
}
