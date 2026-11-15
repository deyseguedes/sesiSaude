import {deflateRawSync} from 'node:zlib'
import {spawn} from 'node:child_process'
import {once} from 'node:events'
import {createServer} from 'node:net'
import {mkdir,mkdtemp,rm,writeFile} from 'node:fs/promises'
import path from 'node:path'
import {fileURLToPath} from 'node:url'
const root=fileURLToPath(new URL('..',import.meta.url)),cache=path.join(root,'node_modules/.cache')
await mkdir(cache,{recursive:true})
const profile=await mkdtemp(path.join(cache,'screen-guide-qa-')),pause=ms=>new Promise(r=>setTimeout(r,ms))
const listener=createServer().listen(0,'127.0.0.1');await once(listener,'listening');const port=listener.address().port;await new Promise(r=>listener.close(r))
const chrome=spawn(process.env.CHROME_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe',['--headless=new','--no-first-run','--no-default-browser-check','--disable-extensions','--disable-background-networking','--window-size=1440,1000','--user-data-dir='+profile,'--remote-debugging-port='+port,'about:blank'],{windowsHide:true,stdio:'ignore'})
let ws
try{
 let page;for(let i=0;i<100&&!page;i++){try{page=(await(await fetch('http://127.0.0.1:'+port+'/json')).json()).find(p=>p.type==='page')}catch{}if(!page)await pause(100)}if(!page)throw Error('Browser unavailable')
 ws=new WebSocket(page.webSocketDebuggerUrl);await once(ws,'open');let id=0;const pending=new Map(),errors=[]
 ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.method==='Runtime.exceptionThrown')errors.push(m.params.exceptionDetails.exception?.description||m.params.exceptionDetails.text);if(pending.has(m.id)){pending.get(m.id)(m.result);pending.delete(m.id)}})
 const send=(method,params={})=>new Promise(resolve=>{const key=++id;pending.set(key,resolve);ws.send(JSON.stringify({id:key,method,params}))})
 const evaluate=async expression=>{const r=await send('Runtime.evaluate',{expression,returnByValue:true,awaitPromise:true});if(r.exceptionDetails)throw Error(r.exceptionDetails.exception?.description||r.exceptionDetails.text);return r.result?.value}
 const wait=async expression=>{for(let i=0;i<150;i++){if(await evaluate(expression))return;await pause(100)}throw Error('Condition failed: '+expression)}
 const click=selector=>evaluate('(()=>{const e=document.querySelector('+JSON.stringify(selector)+');if(!e||e.disabled)throw Error("Action unavailable");e.focus();e.click();return true})()')
 const assert=async(expression,message)=>{if(!await evaluate(expression))throw Error(message)}
 const next=()=>click('[data-testid="screen-guide-card"] .screen-guide-actions .btn-success')
 const start=async()=>{await click('[data-testid="screen-guide-trigger"]');await wait('!!document.querySelector(".screen-guide-dialog[open] [data-testid=screen-guide-card]")')}
 const close=async()=>{await click('[aria-label="Fechar guia"]');await wait('!document.querySelector(".screen-guide-dialog[open]")')}
 const shot=async name=>{const result=await send('Page.captureScreenshot',{format:'png'});await writeFile(path.join(cache,name),Buffer.from(result.data,'base64'))}
 await send('Runtime.enable');await send('Page.enable');await send('Network.enable');await send('Network.setExtraHTTPHeaders',{headers:{'ngrok-skip-browser-warning':'viva-guide-qa'}})


 const origin=process.env.GUIDE_QA_URL||'https://acclaim-heftiness-clever.ngrok-free.dev'
 const api=async(path,method='GET',body)=>evaluate('(async()=>{const s=await(await fetch("/api/v1/auth/session")).json();const r=await fetch("/api/v1"+'+JSON.stringify(path)+',{method:'+JSON.stringify(method)+',headers:{"Content-Type":"application/json","X-CSRF-Token":s.data.csrf_token},'+(body?'body:JSON.stringify('+JSON.stringify(body)+'),':'')+'});return {status:r.status,body:await r.json()}})()')
 await send('Page.navigate',{url:origin+'/login'});await wait('document.querySelectorAll(".demo-profile").length>=8')
 let result=await api('/auth/local-profile','POST',{role:'admin'});if(result.status!==200)throw Error('Profile login')
 await send('Page.navigate',{url:origin+'/import'});await wait('document.querySelector("h1")?.textContent.includes("Importar")')
 const input={rows:[['QA-1','Pessoa de Teste','Empresa fictícia','ATIVO'],['QA-1','Duplicada','Empresa fictícia','ATIVO']],mapping:['registration','name','company','status'],generate:true,preview:true,filename:'qa.csv'}
 result=await api('/aps/import','POST',input);if(result.status!==200||result.body.data.valid!==1||result.body.data.errors.length!==1)throw Error('Import validation '+JSON.stringify(result))
 result=await api('/aps/import','POST',{...input,preview:false});if(result.body.data.imported!==1)throw Error('Import commit')
 await api('/demo/reset','POST')
 const people=await api('/aps/people');if(people.body.data.length!==100||!people.body.data.some(p=>p.registration==='DEMO-0001'))throw Error('Restore baseline '+JSON.stringify(people))

 await api('/auth/local-profile','POST',{role:'nurse'});await api('/demo/reset','POST')
 let q=await api('/demo/outreach/queue');if(q.body.data.items[0]?.registration!=='DEMO-0001')throw Error('Marcos must lead baseline queue')
 let phone=await api('/demo/outreach/phone');if(!phone.body.data.slots.length)throw Error('No compatible shared agenda slots')
 await send('Page.navigate',{url:origin+'/live'});await wait('!!document.querySelector("[data-demo=btn-confirmo]")')
 await wait('[...document.querySelectorAll("button")].some(b=>b.textContent.includes("Ver todas as"))')
 await evaluate('[...document.querySelectorAll("button")].find(b=>b.textContent.includes("Ver todas as")).click();true');await wait('!!document.querySelector("#convocation-search")')
 await evaluate('(()=>{const s=document.querySelector("#convocation-search");s.value="Marcos";s.dispatchEvent(new Event("input",{bubbles:true}));return true})()');await wait('document.querySelectorAll("[data-demo^=fila-item-]").length===1')
 await evaluate('[...document.querySelectorAll("button")].find(b=>b.textContent.includes("Mostrar cinco primeiras")).click();true')
 await click('[data-demo="btn-confirmo"]');await wait('!document.querySelector("[data-demo=fila-item-DEMO-0001]")')
 q=await api('/demo/outreach/queue');if(q.body.data.items.some(p=>p.registration==='DEMO-0001'))throw Error('Confirmation stays in queue')
 const savedCookies=await send('Network.getAllCookies');await send('Network.clearBrowserCookies')
 await api('/auth/local-profile','POST',{role:'nurse'});q=await api('/demo/outreach/queue');if(q.body.data.items[0]?.registration!=='DEMO-0001')throw Error('Visitor state leaked')
 await send('Network.setCookies',{cookies:savedCookies.cookies.map(({name,value,domain,path,secure,httpOnly,sameSite,expires})=>({name,value,domain,path,secure,httpOnly,...(sameSite?{sameSite}:{}),...(expires>0?{expires}:{})}))})
 q=await api('/demo/outreach/queue');if(q.body.data.items.some(p=>p.registration==='DEMO-0001'))throw Error('First session lost state')
 await api('/demo/reset','POST');phone=await api('/demo/outreach/phone')
 let res=await api('/demo/outreach/reply','POST',{choice:'reschedule',slot_id:Number(phone.body.data.slots[0].id)});if(res.status!==200||res.body.data.phone.state!=='rescheduled')throw Error('Reschedule')
 await api('/demo/reset','POST');await api('/demo/outreach/reply','POST',{choice:'cannot',barrier:'schedule'})
 q=await api('/demo/outreach/queue');if(q.body.data.items[0]?.barrier!=='schedule')throw Error('Voluntary barrier')
 await api('/demo/reset','POST');console.log('PASS task 4: live confirmation, compatible reschedule, barrier and independent visitor sessions')


 await api('/auth/local-profile','POST',{role:'admin'});await api('/demo/reset','POST');let dash=await api('/demo/outreach/dashboard');const beforeReplies=dash.body.data.funnel[2].value
 await api('/demo/outreach/reply','POST',{choice:'confirm'});dash=await api('/demo/outreach/dashboard');if(dash.body.data.funnel[2].value!==beforeReplies+1)throw Error('Funnel did not change')
 await api('/auth/local-profile','POST',{role:'manager'});const bulletin=await api('/demo/outreach/dashboard');if(!bulletin.body.data)throw Error('Corporate response '+JSON.stringify(bulletin));if(bulletin.body.data.kind!=='company'||bulletin.body.data.levels||bulletin.body.data.barriers||bulletin.body.data.recognition)throw Error('Corporate clinical disclosure')
 const small=await api('/demo/outreach/dashboard?from=2026-11-25&to=2026-11-25');if(!small.body.data.suppressed)throw Error('Small group not suppressed')
 await api('/auth/local-profile','POST',{role:'admin'});await api('/demo/reset','POST');console.log('PASS task 6: live funnel, corporate separation and API suppression')

 const campaigns=await api('/experiments');const expected=['maintain','redesign','pause','adjust','wait','wait'];if(campaigns.body.data.length<6)throw Error('Missing six campaigns')
 for(let i=0;i<6;i++)if(campaigns.body.data[i].decision.code!==expected[i])throw Error('Unexpected campaign decision '+i)
 const priorLive=campaigns.body.data[5].statistics.scheduled;await api('/demo/outreach/reply','POST',{choice:'confirm'});const changedLive=await api('/experiments/6');if(changedLive.body.data.statistics.scheduled!==priorLive+1)throw Error('Live campaign not updated')
 await send('Page.navigate',{url:origin+'/campaign-management/6'});await wait('!!document.querySelector("[data-demo=campanha-detalhe]")')
 if((await api('/experiments/6/decision','POST',{decision:'wait',justification:'Validação fictícia: aguardar período e amostra.'})).status!==200)throw Error('Decision audit')
 if(!(await api('/experiments/export')).body.data.csv.includes('Convocação'))throw Error('Campaign CSV export')
 await api('/demo/reset','POST');console.log('PASS tasks 7a-f: six decisions, live attribution, detail UI, decision audit and export')


 await send('Page.navigate',{url:origin+'/entrar/enfermeiro?ir=/live&restaurar=1&gravacao=1'});await wait('location.pathname==="/live" && !!document.querySelector("[data-demo=msg-marcos]")');await wait('!!document.querySelector("[data-demo=fila-item-DEMO-0001]")')
 await assert('document.body.classList.contains("demo-recording")&&!document.querySelector("[data-testid=screen-guide-trigger]")','Recording did not hide guide')
 await send('Emulation.setDeviceMetricsOverride',{width:1280,height:720,deviceScaleFactor:1,mobile:false});await shot('hackathon-live-1280.png')
 await send('Page.navigate',{url:origin+'/entrar/trabalhador?ir=/worker-channel&restaurar=1&gravacao=1'});await wait('location.pathname==="/worker-channel" && !!document.querySelector("[data-demo=btn-confirmo]")')
 await send('Emulation.setDeviceMetricsOverride',{width:375,height:812,deviceScaleFactor:1,mobile:true});await assert('document.documentElement.scrollWidth<=innerWidth','375px overflow');await shot('hackathon-phone-375.png')
 await send('Emulation.clearDeviceMetricsOverride');console.log('PASS task 8: direct profile routes, recording mode, 1280x720 and 375px')


 await wait('!!document.querySelector("[data-testid=semester-eligibility]") && document.querySelector("[data-testid=semester-eligibility]").textContent.includes("2 de 4")')
 await wait('document.querySelector("[data-testid=worker-journey]")?.textContent.includes("60")')
 const ownSemester=await api('/demo/outreach/semester');if(ownSemester.body.data.eligible||ownSemester.body.data.checkins!==2)throw Error('Semester progress mismatch');console.log('PASS task 10: Marcos journey and private semester progress')


 await send('Page.navigate',{url:origin+'/privacy'});await wait('!!document.querySelector("[data-demo=privacidade]")');await assert('document.querySelector("[data-demo=privacidade]").textContent.includes("No protótipo")&&document.querySelector("[data-demo=privacidade]").textContent.includes("No MVP")','Privacy columns')
 await assert('document.querySelector("[data-demo=privacidade] details").open===false','PQC must stay collapsed');console.log('PASS task 11: privacy page separates implemented controls and roadmap')



 await api('/auth/local-profile','POST',{role:'admin'})
 const baseVersion=(await api('/experiments/1')).body.data;const versionBody={...baseVersion.data,kind:baseVersion.kind,name:'Versão de teste QA',cycle:'Ciclo 2',...Object.fromEntries(Object.entries(baseVersion.data.criteria||{}).map(([k,v])=>['filter_'+k,v]))}
 const createdVersion=await api('/experiments/1/versions','POST',versionBody);if(createdVersion.status!==201||createdVersion.body.data.version!==2||createdVersion.body.data.frozen_count!==100)throw Error('Version creation or frozen audience '+JSON.stringify(createdVersion))
 const versionDetail=await api('/experiments/'+createdVersion.body.data.id);if(Number(versionDetail.body.data.parent_id)!==1)throw Error('Version history link')
 console.log('PASS campaign version: cloned configuration, new frozen audience and parent history preserved')

 function crc32(buffer){let crc=0xffffffff;for(const byte of buffer){crc^=byte;for(let k=0;k<8;k++)crc=(crc>>>1)^((crc&1)?0xedb88320:0)}return (crc^0xffffffff)>>>0}
 function zip(entries){const locals=[],central=[];let offset=0;for(const [name,text] of entries){const filename=Buffer.from(name),data=Buffer.from(text),compressed=deflateRawSync(data),crc=crc32(data),head=Buffer.alloc(30);head.writeUInt32LE(0x04034b50,0);head.writeUInt16LE(20,4);head.writeUInt16LE(8,8);head.writeUInt32LE(crc,14);head.writeUInt32LE(compressed.length,18);head.writeUInt32LE(data.length,22);head.writeUInt16LE(filename.length,26);locals.push(head,filename,compressed);const c=Buffer.alloc(46);c.writeUInt32LE(0x02014b50,0);c.writeUInt16LE(20,4);c.writeUInt16LE(20,6);c.writeUInt16LE(8,10);c.writeUInt32LE(crc,16);c.writeUInt32LE(compressed.length,20);c.writeUInt32LE(data.length,24);c.writeUInt16LE(filename.length,28);c.writeUInt32LE(offset,42);central.push(c,filename);offset+=head.length+filename.length+compressed.length}const dir=Buffer.concat(central),end=Buffer.alloc(22);end.writeUInt32LE(0x06054b50,0);end.writeUInt16LE(entries.length,8);end.writeUInt16LE(entries.length,10);end.writeUInt32LE(dir.length,12);end.writeUInt32LE(offset,16);return Buffer.concat([...locals,dir,end])}
 const sheetRows=[['MATRÍCULA','Nome','Empresa','STATUS','Data de nascimento','Risco atual'],['QA-XLSX-1','Pessoa XLSX teste','Empresa fictícia Aurora','ATIVO',32462,3],['QA-XLSX-2','Risco inválido teste','Empresa fictícia Aurora','ATIVO',32462,0]]
 const worksheet='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'+sheetRows.map((row,i)=>'<row r="'+(i+1)+'">'+row.map((v,j)=>typeof v==='number'?'<c r="'+String.fromCharCode(65+j)+(i+1)+'"><v>'+v+'</v></c>':'<c r="'+String.fromCharCode(65+j)+(i+1)+'" t="inlineStr"><is><t>'+v+'</t></is></c>').join('')+'</row>').join('')+'</sheetData></worksheet>'
 const fixture=path.join(cache,'aps-import-qa.xlsx');await writeFile(fixture,zip([['[Content_Types].xml','<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>'],['_rels/.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'],['xl/workbook.xml','<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="APS" sheetId="1" r:id="rId1"/></sheets></workbook>'],['xl/_rels/workbook.xml.rels','<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>'],['xl/worksheets/sheet1.xml',worksheet]]))
 await send('Page.navigate',{url:origin+'/entrar/admin-sesi?ir=/import&gravacao=0'});await wait('!!document.querySelector("#aps-file")')
 const documentNode=await send('DOM.getDocument');const fileNode=await send('DOM.querySelector',{nodeId:documentNode.root.nodeId,selector:'#aps-file'});await send('DOM.setFileInputFiles',{nodeId:fileNode.nodeId,files:[fixture]})
 await wait('[...document.querySelectorAll("button")].some(b=>b.textContent.includes("Validar e mostrar"))')
 await evaluate('[...document.querySelectorAll("button")].find(b=>b.textContent.includes("Validar e mostrar")).click();true')
 await wait('document.body.textContent.includes("1 válidas") && document.body.textContent.includes("1 erros")')
 await evaluate('[...document.querySelectorAll("button")].find(b=>b.textContent.includes("Importar 1 linhas")).click();true')
 await wait('document.body.textContent.includes("1 importadas")')
 await api('/demo/reset','POST');console.log('PASS real XLSX upload: deflate ZIP, mapped headers, Excel dates, zero-risk rejection and valid-line commit')

 if(errors.length)throw Error(errors.join('\n'));console.log('PASS task 2: import preview, valid commit, reset and 100-person baseline with Marcos; no console exceptions')
}finally{if(ws)ws.close();chrome.kill();await Promise.race([once(chrome,'exit'),pause(1000)]);if(path.resolve(profile).startsWith(path.resolve(cache)+path.sep))await rm(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200})}
