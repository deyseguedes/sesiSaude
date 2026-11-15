<?php
// Runs inside the existing isolated database fixture.
use App\Services\{ApsFileReader,ApsReviewService,ApsImportService,Crypto,DemoWorkspace};
function apsXlsxFixture(string $sheet,string $properties='',string $relationships=''):string {
 $file=tempnam(sys_get_temp_dir(),'aps-test-');$zip=new ZipArchive();$zip->open($file,ZipArchive::OVERWRITE);
 $zip->addFromString('xl/workbook.xml','<workbook xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><workbookPr '.$properties.'/><sheets><sheet name="Fonte selecionada" sheetId="7" r:id="chosen"/></sheets></workbook>');
 $zip->addFromString('xl/_rels/workbook.xml.rels',$relationships?:'<Relationships><Relationship Id="chosen" Target="worksheets/sheet7.xml"/></Relationships>');
 $zip->addFromString('xl/worksheets/sheet1.xml','<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>ABA ERRADA</t></is></c></row></sheetData></worksheet>');
 $zip->addFromString('xl/worksheets/sheet7.xml',$sheet);$zip->close();$bytes=file_get_contents($file);unlink($file);return $bytes;
}
$fixture='<worksheet><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>MATRÍCULA</t></is></c><c r="B1" t="inlineStr"><is><t>NOME COMPLETO</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>XLSX-001</t></is></c><c r="B2" t="inlineStr"><is><t>Pessoa fictícia</t></is></c></row>';
for($i=3;$i<=1292;$i++)$fixture.='<row r="'.$i.'"><c r="A'.$i.'" s="1"/></row>';
$fixture.='<row r="1392"><c r="M1392"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>';
$bytes=apsXlsxFixture($fixture,'date1904="true"');$parsed=ApsFileReader::read($bytes,'fonte.xlsx');
$zipFile=tempnam(sys_get_temp_dir(),'aps-fallback-');
try{
 file_put_contents($zipFile,$bytes);$fallback=new App\Services\ApsZipReader();verify($fallback->open($zipFile)&&$fallback->getFromName('xl/worksheets/sheet7.xml')===$fixture,'Fallback without ext-zip reads compressed worksheet correctly');
 $entry=$fallback->statIndex(3);$start=$entry['local']+30+unpack('v',substr($bytes,$entry['local']+26,2))[1]+unpack('v',substr($bytes,$entry['local']+28,2))[1];$fallback->close();
 $corrupt=$bytes;$corrupt[$start+5]=chr(ord($corrupt[$start+5])^255);file_put_contents($zipFile,$corrupt);$fallback=new App\Services\ApsZipReader();$fallback->open($zipFile);
 try{$fallback->getFromName('xl/worksheets/sheet7.xml');throw new RuntimeException('Corrupt compressed source accepted');}catch(InvalidArgumentException){}
}finally{unlink($zipFile);}
verify($parsed['headers']===['MATRÍCULA','NOME COMPLETO']&&count($parsed['rows'])===2,'Server follows workbook relation instead of alphabetical sheet filename');
verify($parsed['source_lines']===[2,1392]&&$parsed['date_system']==='1904','Server ignores 1290 formatted rows and preserves row references and calendar');
verify($parsed['formula_cells'][0]['formula']==='1+1'&&$parsed['rows'][1][12]==='2','Server preserves formula and uses cache without calculation');
try{ApsFileReader::read(apsXlsxFixture('<!DOCTYPE worksheet [<!ENTITY secret SYSTEM "file:///not-read">]><worksheet/>'),'fonte.xlsx');throw new RuntimeException('DTD accepted');}catch(InvalidArgumentException){}
try{ApsFileReader::read(apsXlsxFixture(mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?><!DOCTYPE worksheet [<!ENTITY secret "test">]><worksheet/>','UTF-16','UTF-8')),'fonte.xlsx');throw new RuntimeException('UTF-16 DTD accepted');}catch(InvalidArgumentException){}
try{ApsFileReader::read(apsXlsxFixture($fixture,'','<Relationships><Relationship Id="chosen" Target="https://example.invalid" TargetMode="External"/></Relationships>'),'fonte.xlsx');throw new RuntimeException('External worksheet accepted');}catch(InvalidArgumentException){}
try{ApsFileReader::read(apsXlsxFixture('<worksheet><sheetData><row r="1"><c r="ZZZ1"><v>1</v></c></row></sheetData></worksheet>'),'fonte.xlsx');throw new RuntimeException('Excessive column accepted');}catch(InvalidArgumentException){}
$csv="MATRÍCULA;NOME COMPLETO;EMPRESA;STATUS;SEXO;CPF;PREVISÃO DE RETORNO\nFILE-1;Pessoa fonte;Empresa teste;ATIVO;FEMININO;00000000000;\n;;;;;;23/03/2026\n";
$fileInput=['filename'=>'fonte.csv','original_base64'=>base64_encode($csv),'rows'=>[['TAMPERED','Not trusted']],'headers'=>['Not trusted'],'source_lines'=>[999],'date_system'=>'1904','preview'=>true];
$fileService=new ApsImportService($s,$admin);$preview=$fileService->fileImport($fileInput);
denied(fn()=>$fileService->fileImport(['rows'=>[['untrusted']]]),422);
denied(fn()=>$fileService->inspectFile(['filename'=>'fonte.csv','original_base64'=>'@@@@']),422);
$unknown=$fileService->inspectFile(['filename'=>'colunas.csv','original_base64'=>base64_encode("A;B;C;D\n1;Pessoa;Empresa;ATIVO")]);
verify($unknown['valid']===0&&$unknown['errors'][0]['code']==='MAPPING_REQUIRED'&&count($unknown['source']['headers'])===4,'Missing mappings return editable headers without importing');
$age=$fileService->inspectFile(['filename'=>'idade.csv','original_base64'=>base64_encode("MATRÍCULA;NOME COMPLETO;EMPRESA;STATUS;DATA DE NASCIMENTO;IDADE\nAGE-1;Pessoa fictícia;Empresa;ATIVO;01/01/1980;1")]);
verify(in_array('AGE_CACHE_DIFFERENCE',array_column($age['warnings'],'code'),true)&&$age['preview'][0]['age_source']==='1','Age discrepancy warned without replacing source age');
try{ApsFileReader::read("nome\n\"aspas abertas",'fonte.csv');throw new RuntimeException('Unclosed CSV quote accepted');}catch(InvalidArgumentException){}
verify($preview['source']['server_extracted']&&$preview['valid']===1&&$preview['preview'][0]['registration']==='FILE-1','Original file overrides tampered client rows and headers');
verify($preview['review'][0]['line']===3&&$preview['source']['date_system']==='1900','Original file overrides client row numbers and calendar');
$multiline=ApsFileReader::read("matricula;nome\nA;\"Pessoa\ncom quebra\"\nB;Outra\n",'fonte.csv');verify($multiline['source_lines']===[2,4],'CSV multiline cells retain physical source line');
$fileService->fileImport(array_replace($fileInput,['preview'=>false]));$reviews=new ApsReviewService($s,$admin);$listing=$reviews->listing(['code'=>'ORPHAN_OBSERVATION']);
verify($listing['total']===1,'Orphan persisted as review without person creation');$reviewId=(int)$listing['items'][0]['id'];
$detail=$reviews->detail($reviewId);verify($detail['cells'][0]['value']==='23/03/2026','Review shows original observation with Brazilian date');
$cpfItems=$reviews->listing(['code'=>'CPF_INVALID']);$cpfDetail=$reviews->detail((int)$cpfItems['items'][0]['id']);verify($cpfDetail['cells'][0]['value']==='***.***.***-00','Review masks CPF values');
$updated=$reviews->update($reviewId,['status'=>'reviewed','version'=>1,'note'=>'Conferido: observação continua sem vínculo.']);
verify($updated['version']===2&&$reviews->detail($reviewId)['item']['status']==='reviewed','Technical review records version and note');
denied(fn()=>$reviews->update($reviewId,['status'=>'pending','version'=>1,'note'=>'Tentativa com versão anterior']),409);
$note=$s->one('SELECT note FROM aps_import_reviews WHERE id=?',[$reviewId])['note'];verify(!str_contains($note,'Conferido')&&Crypto::decrypt($note)==='Conferido: observação continua sem vínculo.','Review notes encrypted at rest');
$beforeReviews=(int)$s->one('SELECT COUNT(*) n FROM aps_import_reviews')['n'];$fileService->fileImport(array_replace($fileInput,['preview'=>false]));verify((int)$s->one('SELECT COUNT(*) n FROM aps_import_reviews')['n']===$beforeReviews&&$reviews->detail($reviewId)['item']['status']==='reviewed','Reprocessing does not duplicate or reset reviews');
$otherScope=new ApsReviewService($s,array_replace($admin,['company_id'=>2]));verify($otherScope->listing([])['total']===0,'Review list scoped to operator');denied(fn()=>$otherScope->detail($reviewId),404);denied(fn()=>$otherScope->update($reviewId,['status'=>'reviewed','version'=>2,'note'=>'Outro escopo']),404);
denied(fn()=>(new ApsReviewService($s,$doctor))->listing([]),403);
$visit=DemoWorkspace::key();DemoWorkspace::reset();denied(fn()=>$reviews->detail($reviewId),404);$_SESSION['demo_visit']=$visit;
$archive=$s->one('SELECT archive FROM aps_source_archives ORDER BY id DESC LIMIT 1')['archive'];$original=json_decode(Crypto::decrypt($archive),true);
verify(base64_decode($original['original_base64'])===$csv&&$original['original_sha256']===hash('sha256',$csv),'Server archives exact original bytes and SHA-256');
if($s->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'){
 $s->db->exec("CREATE TRIGGER aps_archive_failure BEFORE INSERT ON aps_source_archives BEGIN SELECT RAISE(ABORT,'simulated failure'); END");
 try{$fileService->fileImport(['filename'=>'rollback.csv','original_base64'=>base64_encode(str_replace('FILE-1','ROLLBACK-1',$csv)),'preview'=>false]);throw new RuntimeException('Simulated archive failure ignored');}catch(PDOException){}finally{$s->db->exec('DROP TRIGGER aps_archive_failure');}
 verify($s->one('SELECT id FROM aps_people WHERE registration=?',['ROLLBACK-1'])===null,'Archive failure rolls back person changes');
}
