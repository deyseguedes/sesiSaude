import argparse,zipfile,xml.etree.ElementTree as ET,json,hashlib,re,posixpath
from collections import Counter
from pathlib import Path
N={'m':'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
R='http://schemas.openxmlformats.org/officeDocument/2006/relationships'
def inspect(path):
 data=Path(path).read_bytes()
 with zipfile.ZipFile(path) as z:
  if any(i.file_size>20_000_000 for i in z.infolist()): raise ValueError('XML exceeds audit limit')
  def xml(name):
   raw=z.read(name)
   if b'<!DOCTYPE' in raw: raise ValueError('DTD not allowed')
   return ET.fromstring(raw)
  wb=xml('xl/workbook.xml')
  rel={e.get('Id'):e.get('Target') for e in xml('xl/_rels/workbook.xml.rels')}
  shared=[]
  if 'xl/sharedStrings.xml' in z.namelist():
   shared=[''.join(si.itertext()) for si in xml('xl/sharedStrings.xml').findall('m:si',N)]
  styles=xml('xl/styles.xml') if 'xl/styles.xml' in z.namelist() else None
  formats={int(e.get('numFmtId')):e.get('formatCode') for e in styles.findall('m:numFmts/m:numFmt',N)} if styles is not None else {}
  xfs=[int(e.get('numFmtId')) for e in styles.findall('m:cellXfs/m:xf',N)] if styles is not None else []
  sheets=[]
  for sheet in wb.findall('m:sheets/m:sheet',N):
   target=rel[sheet.get('{'+R+'}id')];target=target.lstrip('/') if target.startswith('/') else posixpath.normpath('xl/'+target)
   root=xml(target);rows=[]
   for row in root.findall('m:sheetData/m:row',N):
    cells=[]
    for c in row.findall('m:c',N):
     t=c.get('t','n');v=c.find('m:v',N);value=v.text if v is not None and v.text is not None else ''
     if t=='s':value=shared[int(value)] if value else ''
     elif t=='inlineStr':value=''.join(c.find('m:is',N).itertext()) if c.find('m:is',N) is not None else ''
     cells.append({'ref':c.get('r'),'type':t,'style':int(c.get('s','0')),'value':value,'formula':c.find('m:f',N) is not None,'formula_text':c.find('m:f',N).text if c.find('m:f',N) is not None else None})
    rows.append({'row':int(row.get('r')),'cells':cells})
   candidates=[]
   for row in rows[:20]:
    vals=[c['value'] for c in row['cells'] if c['value'].strip()]
    hits=sum(any(k in v.casefold() for k in ['matr','nome','cpf','telefone','empresa','status','risco']) for v in vals)
    if hits>=3:candidates.append({'row':row['row'],'headers':[{'ref':c['ref'],'value':c['value']} for c in row['cells'] if c['value'].strip()]})
   sheets.append({'name':sheet.get('name'),'state':sheet.get('state','visible'),'dimension':root.find('m:dimension',N).get('ref') if root.find('m:dimension',N) is not None else None,'row_count_xml':len(rows),'first_row_counts':[{'row':r['row'],'filled':sum(bool(c['value'].strip()) for c in r['cells'])} for r in rows[:12]],'header_candidates':candidates,'merged':[e.get('ref') for e in root.findall('m:mergeCells/m:mergeCell',N)],'formula_count':sum(c['formula'] for r in rows for c in r['cells']),'rows':rows})
 return {'sha256':hashlib.sha256(data).hexdigest(),'bytes':len(data),'date1904':wb.find('m:workbookPr',N).get('date1904','0') if wb.find('m:workbookPr',N) is not None else '0','formats':formats,'style_formats':xfs,'has_vba':any('vbaproject' in n.lower() for n in z.namelist()),'sheets':sheets}

import datetime,csv,io,unicodedata
from collections import defaultdict
def cpf_valid(raw):
 value=re.sub(r'\D','',str(raw))
 if len(value)!=11 or len(set(value))==1:return False
 numbers=list(map(int,value))
 for n in [9,10]:
  digit=sum(numbers[i]*(n+1-i) for i in range(n))*10%11
  if digit==10:digit=0
  if digit!=numbers[n]:return False
 return True
def excel_date(value,date1904=False):
 number=float(value)
 if date1904:return (datetime.datetime(1904,1,1)+datetime.timedelta(days=number)).date()
 if not 1<=number<=73415 or int(number)==60:raise ValueError('Invalid Excel date')
 origin=datetime.datetime(1899,12,31) if number<60 else datetime.datetime(1899,12,30)
 return (origin+datetime.timedelta(days=number)).date()
def audit(path,reference):
 book=inspect(path)
 if len(book['sheets'])!=1:raise ValueError('Audit profile expects the documented single sheet; inspect workbook metadata first')
 sheet=book['sheets'][0];candidates=sheet['header_candidates']
 if not candidates:raise ValueError('No header identified')
 header=candidates[0];headers={re.sub(r'\d+$','',c['ref']):c['value'] for c in header['headers']}
 if len(headers)!=32 or headers.get('A')!='MATRÍCULA' or headers.get('F')!='CPF':raise ValueError('Schema differs from this audited source profile')
 nonempty=[r for r in sheet['rows'] if r['row']>header['row'] and any(c['value'].strip() or c['formula'] for c in r['cells'])]
 people=[];orphans=[]
 for r in nonempty:
  cells={re.sub(r'\d+$','',c['ref']):c for c in r['cells']}
  if any(cells.get(col,{}).get('value','').strip() for col in 'ABCDEFGHIJK'):people.append(r)
  else:orphans.append({'row':r['row'],'cells':[{'ref':c['ref'],'type':c['type'],'date_style':book['style_formats'][c['style']] in [14,15,16,17,18,19,20,21,22,166]} for c in r['cells'] if c['value'].strip()]})
 columns=[];by_col={col:[] for col in headers}
 for r in people:
  cells={re.sub(r'\d+$','',c['ref']):c for c in r['cells']}
  for col in headers:by_col[col].append((r['row'],cells.get(col,{'value':'','type':'absent','formula':False,'style':0})))
 for col,name in headers.items():
  observations=by_col[col];filled=[(r,c) for r,c in observations if c['value'].strip()]
  result={'column':col,'header':name,'filled':len(filled),'empty':len(people)-len(filled),'percent':100*len(filled)/len(people) if people else None,'types':dict(Counter(c['type'] for _,c in filled)),'formula_count':sum(c['formula'] for _,c in observations)}
  if col in ['C','G','L','Y','Z','AD']:result['categories']=dict(Counter(c['value'] for _,c in filled))
  if col in ['J','K']:result['distinct']=len(set(c['value'] for _,c in filled));result['group_sizes']=sorted(Counter(c['value'] for _,c in filled).values(),reverse=True)
  if col in ['D','H']:
   dates=[];invalid=[];future=[]
   for row,c in filled:
    try:
     date=excel_date(c['value'],book['date1904']=='1') if c['type']=='n' else datetime.datetime.strptime(c['value'],'%d/%m/%Y').date()
     dates.append(date)
     if date>reference:future.append(row)
    except (ValueError,OverflowError):invalid.append(row)
   result.update(invalid_rows=invalid,future_rows=future,date_year_range=[min(dates).year,max(dates).year] if dates else None)
  columns.append(result)
 duplicates={}
 for col in ['A','B','F','I']:
  groups=defaultdict(list)
  for row,c in by_col[col]:
   value=c['value'].strip()
   if col in ['F','I']:value=re.sub(r'\D','',value)
   if col=='B':value=' '.join(''.join(ch for ch in unicodedata.normalize('NFKD',value) if not unicodedata.combining(ch)).casefold().split())
   if value:groups[value].append(row)
  duplicates[col]={'distinct':len(groups),'duplicate_row_groups':[v for v in groups.values() if len(v)>1]}
 age_differences=[];entry_before_birth=[];invalid_identifiers=[];invalid_phones=[]
 for r in people:
  cells={re.sub(r'\d+$','',c['ref']):c for c in r['cells']}
  if not cpf_valid(cells.get('F',{}).get('value','')):invalid_identifiers.append(r['row'])
  if len(re.sub(r'\D','',cells.get('I',{}).get('value',''))) not in [10,11]:invalid_phones.append(r['row'])
  try:
   birth=excel_date(cells['D']['value'],book['date1904']=='1');entry=excel_date(cells['H']['value'],book['date1904']=='1')
   age=reference.year-birth.year-((reference.month,reference.day)<(birth.month,birth.day))
   if float(cells['E']['value'])!=age:age_differences.append(r['row'])
   if entry<birth:entry_before_birth.append(r['row'])
  except (ValueError,KeyError):pass
 with zipfile.ZipFile(path) as z:
  wb=ET.fromstring(z.read('xl/workbook.xml'));links={e.get('Id'):e.get('Target') for e in ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))}
  sh=wb.find('m:sheets/m:sheet',N);target=links[sh.get('{'+R+'}id')];target=target.lstrip('/') if target.startswith('/') else posixpath.normpath('xl/'+target);root=ET.fromstring(z.read(target))
  validations=[{'type':v.get('type'),'ranges':v.get('sqref'),'formula1':v.find('m:formula1',N).text if v.find('m:formula1',N) is not None else None} for v in root.findall('m:dataValidations/m:dataValidation',N)]
  formulas=Counter(re.sub(r'([A-Z]+)\d+',r'\1{row}',f.text or '') for f in root.findall('m:sheetData/m:row/m:c/m:f',N) if f.text)
  external_links=sum(n.startswith('xl/externalLinks/') for n in z.namelist())
 filled=sum(c['filled'] for c in columns);expected=len(people)*len(headers)
 return {'profile':'APS SESI source diagnostic, not an ETL importer','source_file':Path(path).name,'sha256':book['sha256'],'bytes':book['bytes'],'reference_date':reference.isoformat(),'sheet':sheet['name'],'dimension':sheet['dimension'],'header_row':header['row'],'column_count':len(headers),'worker_count':len(people),'worker_row_range':[min(r['row'] for r in people),max(r['row'] for r in people)],'nonempty_rows':len(nonempty),'empty_xml_rows':len(sheet['rows'])-1-len(nonempty),'orphan_rows':orphans,'date_system':'1904' if book['date1904']=='1' else '1900','macro_present':book['has_vba'],'external_links':external_links,'merged_cells':sheet['merged'],'columns':columns,'filled_cells':filled,'expected_cells':expected,'fill_percent':100*filled/expected if expected else None,'duplicates':duplicates,'invalid_cpf_rows':invalid_identifiers,'invalid_phone_format_rows':invalid_phones,'age_cache_difference_rows':age_differences,'entry_before_birth_rows':entry_before_birth,'formula_count':sheet['formula_count'],'formula_patterns':dict(formulas),'validations':validations}
def self_test():
 assert cpf_valid('52998224725')
 assert cpf_valid('11144477735')
 assert not cpf_valid('00000000000')
 assert not cpf_valid('52998224726')
 assert excel_date(1)==datetime.date(1900,1,1)
 assert excel_date(46341)==datetime.date(2026,11,15)
 assert excel_date(0,True)==datetime.date(1904,1,1)
 try:excel_date(60)
 except ValueError:pass
 else:raise AssertionError('Fictitious Excel leap day accepted')
if __name__=='__main__':
 parser=argparse.ArgumentParser(description='Read-only diagnostic; never executes formulas or imports rows into a database.')
 parser.add_argument('file',nargs='?');parser.add_argument('--reference-date',default='2026-11-15');parser.add_argument('--output');parser.add_argument('--self-test',action='store_true');args=parser.parse_args()
 self_test()
 if args.self_test and not args.file:print('OK: CPF, date systems and invalid leap-day checks');raise SystemExit()
 if not args.file:parser.error('source XLSX is required')
 result=audit(args.file,datetime.date.fromisoformat(args.reference_date))
 serialized=json.dumps(result,ensure_ascii=False,indent=2)
 if args.output:
  destination=Path(args.output);destination.parent.mkdir(parents=True,exist_ok=True);destination.write_text(serialized,encoding='utf8')
 print(json.dumps({k:result[k] for k in ['sha256','worker_count','column_count','nonempty_rows','empty_xml_rows','filled_cells','expected_cells','fill_percent']},ensure_ascii=True))
 print(json.dumps({'invalid_cpf_count':len(result['invalid_cpf_rows']),'age_cache_difference_count':len(result['age_cache_difference_rows']),'orphan_rows':result['orphan_rows']},ensure_ascii=True))
