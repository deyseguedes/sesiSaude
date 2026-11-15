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
 const requests=[];ws.addEventListener('message',e=>{const m=JSON.parse(e.data);if(m.method==='Network.requestWillBeSent')requests.push({method:m.params.request.method,url:m.params.request.url})})
 await send('Page.navigate',{url:origin+'/login'});await wait('document.querySelectorAll(".demo-profile").length===8')
 const ensure=async()=>{if(!await evaluate('!!document.querySelector(".screen-guide-dialog[open]")'))await start()}
 const choose=async target=>{await evaluate('(()=>{const e=document.querySelector("#guide-screen");e.value='+JSON.stringify(target)+';e.dispatchEvent(new Event("change",{bubbles:true}));return true})()');await wait('location.pathname==='+JSON.stringify(target)+' && !!document.querySelector(".screen-guide-dialog[open]")');await pause(400)}
 let visits=0
 for(const [i,role] of ['admin','manager','doctor','nurse','navigator','worker','dependent','leader'].entries()){
  await evaluate('(()=>{const name='+JSON.stringify({admin:'Administrador SESI',manager:'Gestor empresarial',doctor:'Médico',nurse:'Enfermeiro',navigator:'Navegador de Cuidado',worker:'Trabalhador',dependent:'Dependente',leader:'Líder de setor'}[role])+';[...document.querySelectorAll(".demo-profile")].find(e=>e.textContent.includes(name)).click();return true})()');await wait('!!document.querySelector(".workspace-content h1")')
  await ensure()
  const routes=await evaluate('[...document.querySelectorAll("#guide-screen option")].map(o=>o.value)')
  for(const route of routes){
   await choose(route);await assert('!!document.querySelector("[data-testid=screen-guide-card] h2")','Missing guide: '+role+' '+route)
   await assert('!document.querySelector("[data-testid=screen-guide-card] h2").textContent.includes("Entenda esta tela")','Generic fallback: '+route)
   visits++
  }
  if(role==='admin'){
   await choose('/management')
   for(const tab of ['people','structure','contracts','agenda','access']){
    await click('.section-tabs [data-guide-tab="'+tab+'"]')
    await wait('document.querySelector("[data-guide-management-tab]")?.dataset.guideManagementTab==='+JSON.stringify(tab));await pause(400)
    await assert('!!document.querySelector(".screen-guide-dialog[open]")','Guide lost after tab')
   }
   await click('.section-tabs [data-guide-tab="contracts"]');await pause(500)
   await next();await next();await wait('!!document.querySelector("[data-testid=screen-guide-interact]:not(:disabled)")')
   await click('[data-testid="screen-guide-interact"]')
   await wait('document.querySelector(".screen-guide-heading")?.textContent.includes("CAMPO A CAMPO")')
   await assert('!!document.querySelector("dialog.side-panel[open] .screen-guide-dialog[open]")','Form guide is not inside active modal')
   const before=requests.filter(r=>['POST','PUT'].includes(r.method)&&r.url.includes('/management/contracts')).length
   await next();await click('[data-testid="screen-guide-interact"]');await send('Input.insertText',{text:'Rascunho do guia, sem salvar'})
   await assert('document.querySelector("dialog.side-panel[open] form input[type=text]").value==="Rascunho do guia, sem salvar"','Cannot type with guide open')
   await next()
   if(requests.filter(r=>['POST','PUT'].includes(r.method)&&r.url.includes('/management/contracts')).length!==before)throw Error('Guide submitted a contract')
   await click('dialog.side-panel[open] [aria-label="Fechar painel"]');await wait('!document.querySelector("dialog.side-panel[open]")')
   await assert('!!document.querySelector(".screen-guide-dialog[open]")','Guide did not return after closing form')
   console.log('PASS management tabs, guided form opening and interactive typing without saving')
  }
  if(role==='doctor'){
   await choose('/patients');await wait('!!document.querySelector(".patient-link")');await next();await next();await wait('!!document.querySelector("[data-testid=screen-guide-interact]:not(:disabled)")');await click('[data-testid="screen-guide-interact"]')
   await wait('/^\\/patients\\/\\d+$/.test(location.pathname) && !!document.querySelector("[data-guide-patient-section]")');await pause(500)
   for(const [index,section] of [[0,'records'],[1,'indicators'],[3,'goals'],[4,'overview']]){
    await evaluate('document.querySelectorAll(".clinical-quick-actions button")['+index+'].click();true')
    await wait('document.querySelector("[data-guide-patient-section]")?.dataset.guidePatientSection==='+JSON.stringify(section));await pause(350)
    await assert('!!document.querySelector(".screen-guide-dialog[open]")','Clinical section lost guide')
   }
   console.log('PASS guided patient navigation and clinical subsections')
  }
  if(role==='nurse'){
   await choose('/');await wait('!!document.querySelector(".worklist-person button")');await next();await next();await next();await wait('!!document.querySelector("[data-testid=screen-guide-interact]:not(:disabled)")');await click('[data-testid="screen-guide-interact"]')
   await wait('!!document.querySelector("dialog.side-panel[open] .summary-facts")');await pause(500)
   await evaluate('(()=>{const e=[...document.querySelectorAll("dialog.side-panel[open] button")].find(e=>e.textContent==="Registrar contato");e.click();return true})()')
   await wait('document.querySelector(".screen-guide-heading")?.textContent.includes("CAMPO A CAMPO")')
   await next();await next();await next();await click('[data-testid="screen-guide-interact"]');await send('Input.insertText',{text:'Rascunho ficticio de contato, sem gravar'})
   await assert('document.querySelector("dialog.side-panel[open] textarea").value==="Rascunho ficticio de contato, sem gravar"','Cannot type in guided follow-up')
   await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await pause(500)
   await assert('(()=>{const r=document.querySelector("[data-testid=screen-guide-card]").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()','Mobile card clipped')
   await shot('guide-interactive-mobile.png');await close()
   await assert('document.querySelector("dialog.side-panel[open] textarea").value==="Rascunho ficticio de contato, sem gravar"','Guide discarded draft')
   await click('dialog.side-panel[open] [aria-label="Fechar painel"]');await send('Emulation.clearDeviceMetricsOverride')
   console.log('PASS guided follow-up opening, field interaction, draft preservation and mobile')
  }
  if(await evaluate('!!document.querySelector(".screen-guide-dialog[open]")'))await close()
  await click('.user-summary button');await wait('document.querySelectorAll(".demo-profile").length===8')
  console.log('PASS every available screen: '+role)
 }
 await send('Page.navigate',{url:origin+'/system'});await wait('!!document.querySelector("#connection-title")');await start();await assert('document.querySelector("[data-testid=screen-guide-card] h2").textContent.includes("Status")','System guide missing');await close()
 await send('Page.navigate',{url:origin+'/register'});await wait('!!document.querySelector("#main-content h1")');await start();await assert('document.querySelector("[data-testid=screen-guide-card] h2").textContent.includes("convite")','Registration guide missing');await close()
 await send('Page.navigate',{url:origin+'/guide-page-not-found'});await wait('document.querySelector("#main-content h1")?.textContent.includes("encontrada")');await start();await assert('document.querySelector("[data-testid=screen-guide-card] h2").textContent.includes("encontrada")','Not-found guide missing');await close()
 if(errors.length)throw Error(errors.join('\n'))
 console.log('PASS '+visits+' authorized screen/profile combinations, system, registration and not-found; no browser exceptions')
}finally{if(ws)ws.close();chrome.kill();await Promise.race([once(chrome,'exit'),pause(1000)]);if(path.resolve(profile).startsWith(path.resolve(cache)+path.sep))await rm(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200})}
