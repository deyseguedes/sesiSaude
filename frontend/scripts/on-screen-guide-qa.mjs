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
 await send('Page.navigate',{url:origin+'/login'});await wait('document.querySelectorAll(".demo-profile").length===7')
 await start();await assert('document.querySelector("progress").value===1','Guest tour did not start');await next();await assert('document.querySelector("progress").value===2','Next did not advance')
 await click('[data-testid="screen-guide-card"] .btn-outline-secondary');await assert('document.querySelector("progress").value===1','Previous did not return')
 await send('Input.dispatchKeyEvent',{type:'keyDown',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await send('Input.dispatchKeyEvent',{type:'keyUp',key:'Escape',code:'Escape',windowsVirtualKeyCode:27});await wait('!document.querySelector(".screen-guide-dialog[open]")')
 console.log('PASS guest guide: next, previous and Escape')
 await start();const before=await evaluate('location.href');await click('.screen-guide-manual-link')
 await wait('!!document.querySelector(".screen-guide-manual[open] iframe")')
 await wait('document.querySelector(".screen-guide-manual iframe").contentDocument?.querySelectorAll("[data-chapter]").length===19')
 await assert('location.href==='+JSON.stringify(before),'Manual navigated away')
 await assert('document.querySelector(".screen-guide-manual iframe").contentDocument.body.dataset.embedded==="true"','Manual embed mode missing')
 await click('.screen-guide-manual[open] [aria-label="Fechar painel"]');await wait('!document.querySelector(".screen-guide-manual[open]")')
 console.log('PASS full manual opens and closes inside current screen')
 const roles=['admin','manager','doctor','nurse','navigator','worker','dependent']
 for(let i=0;i<roles.length;i++){
  await evaluate('document.querySelectorAll(".demo-profile")['+i+'].click();true')
  await wait('!!document.querySelector(".workspace-content h1")')
  await start();await wait('!!document.querySelector("[data-testid=screen-guide-highlight]")')
  if(roles[i]==='doctor'){await next();await shot('on-screen-guide-desktop.png')}
  const maximum=await evaluate('document.querySelector("progress").max')
  for(let step=await evaluate('document.querySelector("progress").value');step<=maximum;step++)await next()
  await wait('!document.querySelector(".screen-guide-dialog[open]")')
  if(roles[i]==='nurse'){
   await wait('!!document.querySelector(".worklist-row .worklist-person button") && !document.querySelector(".workspace-content > p[role=status]")')
   await click('.worklist-row .worklist-person button')
   await wait('!!document.querySelector("dialog.side-panel[open] .summary-facts")')
   const selected=await evaluate('document.querySelector("dialog.side-panel[open]").dataset.followupId')
   await click('dialog.side-panel[open] [aria-label="Guia deste painel"]');await wait('!!document.querySelector(".screen-guide-dialog[open]")');await next()
   await assert('document.querySelector("[data-testid=screen-guide-card] h2").textContent.includes("mesma ficha")','Follow-up guide lost its context')
   await close();await assert('document.querySelector("dialog.side-panel[open]").dataset.followupId==='+JSON.stringify(selected),'Guide lost open follow-up sheet')
   await click('dialog.side-panel[open] [aria-label="Fechar painel"]');await wait('!document.querySelector("dialog.side-panel[open]")')
   await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true})
   await start();await next();await pause(400)
   await assert('(()=>{const r=document.querySelector("[data-testid=screen-guide-card]").getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight})()','Mobile guide card clipped')
   await assert('document.documentElement.scrollWidth<=innerWidth','Mobile guide overflow')
   await shot('on-screen-guide-mobile.png');await close();await send('Emulation.clearDeviceMetricsOverride')
   console.log('PASS follow-up sheet help and 390px mobile guide')
  }
  await click('.user-summary button');await wait('document.querySelectorAll(".demo-profile").length===7')
  console.log('PASS contextual walkthrough: '+roles[i])
 }
 if(errors.length)throw Error(errors.join('\n'))
 console.log('PASS no browser runtime errors; no business records submitted by the guide')
}finally{if(ws)ws.close();chrome.kill();await Promise.race([once(chrome,'exit'),pause(1000)]);if(path.resolve(profile).startsWith(path.resolve(cache)+path.sep))await rm(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200})}
