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
 if(errors.length)throw Error(errors.join('\n'));console.log('PASS task 2: import preview, valid commit, reset and 100-person baseline with Marcos; no console exceptions')
}finally{if(ws)ws.close();chrome.kill();await Promise.race([once(chrome,'exit'),pause(1000)]);if(path.resolve(profile).startsWith(path.resolve(cache)+path.sep))await rm(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200})}
