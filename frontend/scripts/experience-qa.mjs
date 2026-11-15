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
 const go=async path=>{await send('Page.navigate',{url:origin+path});await wait('!!document.querySelector(".workspace-content h1")')}
 const login=async name=>{await send('Page.navigate',{url:origin+'/login'});await wait('document.querySelectorAll(".demo-profile").length===8');await evaluate('(()=>{[...document.querySelectorAll(".demo-profile")].find(e=>e.textContent.includes('+JSON.stringify(name)+')).click();return true})()');await wait('!!document.querySelector(".user-summary") && location.pathname==="/"');await pause(400)}
 const logout=async()=>{await click('.user-summary button');await wait('document.querySelectorAll(".demo-profile").length===8')}
 const api=async(path,method='GET',body)=>evaluate('(async()=>{const session=await(await fetch("/api/v1/auth/session")).json();const r=await fetch("/api/v1"+'+JSON.stringify(path)+',{method:'+JSON.stringify(method)+',headers:{"Content-Type":"application/json","X-CSRF-Token":session.data.csrf_token},'+(body?'body:JSON.stringify('+JSON.stringify(body)+'),':'')+'});return {status:r.status,body:await r.json()}})()')
 await login('Administrador SESI')
 await wait('document.querySelector("[data-testid=management-insights]")?.textContent.includes("54,9%")')
 await assert('document.querySelector("[data-testid=management-insights]").textContent.includes("46,0%") && document.querySelector("[data-testid=management-insights]").textContent.includes("8,9")','Recognition example values missing')
 await shot('experience-management.png')
 const management=await api('/demo/management')
 if(management.body.data.funnel[0].value!==100||management.body.data.recognition.result.proposed_percent!==3)throw Error('Incorrect synthetic counts or recognition')
 console.log('PASS management: 100 people, three levels, 54.9%, 46.0%, +8.9pp, 3% and gates')
 await logout()
 await login('Trabalhador')
 await wait('!!document.querySelector("[data-testid=semester-eligibility]")')
 if((await api('/demo/management')).status!==403)throw Error('Worker accessed management')
 await go('/worker-channel');await wait('document.querySelectorAll("#channel-person option").length===2')
 await evaluate('(()=>{[...document.querySelectorAll("[data-testid=worker-channel] button")].find(e=>e.textContent.includes("Eu vou")).click();return true})()');await wait('!!document.querySelector(".alert-success")')
 const channel=await api('/demo/channel'),carla=channel.body.data.people.find(p=>p.display_name==='Carla'),jose=channel.body.data.people.find(p=>p.display_name==='Seu José')
 if(!carla||!jose)throw Error('Missing accessible scenarios')
 const confirmed=await api('/demo/channel/reply','POST',{patient_id:Number(carla.id),choice:'confirm',leader_consent:true})
 if(confirmed.status!==200)throw Error('Confirmation failed '+JSON.stringify(confirmed.body))
 const audio=await api('/demo/channel/reply','POST',{patient_id:Number(jose.id),choice:'audio_help',leader_consent:false})
 if(audio.status!==200)throw Error('Audio response failed '+JSON.stringify(audio.body))
 await evaluate('(()=>{const s=document.querySelector("#channel-person");s.value='+JSON.stringify(String(jose.id))+';s.dispatchEvent(new Event("change",{bubbles:true}));return true})()')
 await evaluate('(()=>{[...document.querySelectorAll("[data-testid=worker-channel] button")].find(e=>e.textContent.includes("Enviar este áudio")).click();return true})()');await wait('document.querySelector(".alert-success")?.textContent.includes("áudio simulado")')
 await send('Emulation.setDeviceMetricsOverride',{width:390,height:844,deviceScaleFactor:1,mobile:true});await pause(300)
 await assert('document.querySelector("[data-testid=worker-channel]").getBoundingClientRect().width<=390','Mobile overflow')
 await shot('experience-worker-mobile.png')
 await send('Emulation.clearDeviceMetricsOverride')
 console.log('PASS accessible channel: Carla confirmation and Jose audio request persisted; mobile layout')
 await logout()
 await login('Enfermeiro')
 await wait('document.querySelector("h1")?.textContent==="Fila do dia"')
 if((await api('/management/overview')).status!==403)throw Error('Nurse accessed contracts or invitations')
 await assert('![...document.querySelectorAll("nav a")].some(a=>a.getAttribute("href")==="/management")','Nurse management link')
 let queue=await api('/nursing/worklist')
 if(!queue.body.data.items.some(i=>Number(i.patient_id)===Number(jose.id)&&i.kind==='worker_channel'&&Number(i.help_requested)===1))throw Error('Audio request absent in queue')
 if(queue.body.data.items.some(i=>!i.service_unit))throw Error('Unspecified unit remains')
 const cards=await evaluate('[...document.querySelectorAll(".worklist-person > button:first-child")].map(e=>e.textContent)')
 if(new Set(cards).size!==cards.length)throw Error('Repeated patient in grouped queue')
 await evaluate('(()=>{const input=document.querySelector(".form-check input");input.checked=false;input.dispatchEvent(new Event("change",{bubbles:true}));const search=document.querySelector("#queue-search");search.value='+JSON.stringify(jose.name)+';search.dispatchEvent(new Event("input",{bubbles:true}));return true})()')
 const scriptReply=await api('/demo/channel/reply','POST',{patient_id:Number(jose.id),choice:'call',leader_consent:false})
 await wait('[...document.querySelectorAll(".worklist-row")].some(row=>row.dataset.itemId==='+JSON.stringify(String(scriptReply.body.data.item_id))+' && row.textContent.includes("Solicitação de ligação"))')
 await shot('experience-nursing.png')
 console.log('PASS nursing: received channel events, units populated, grouped patients, management API denied')
 await logout()
 await login('Médico')
 await wait('document.querySelector("h1")?.textContent==="Pacientes acompanhados"')
 await assert('!document.querySelector("[data-testid=team-recognition]")','Recognition displaces portfolio')
 await click('.patient-link');await wait('!!document.querySelector("[data-testid=patient-summary] .summary-facts")')
 await assert('!document.querySelector("[data-testid=patient-summary]").textContent.includes("Texto demonstrativo sem dados reais.")','Repetitive filler remains')
 console.log('PASS doctor opens portfolio and patient summary')
 await logout()
 await login('Líder de setor')
 await wait('document.querySelector("h1")?.textContent.includes("Apoiar")')
 const leader=await api('/demo/leader')
 if(leader.body.data.authorized.length!==1||!leader.body.data.authorized[0].name.includes('Carla'))throw Error('Consent did not filter leader list')
 if(Object.keys(leader.body.data.authorized[0]).sort().join(',')!=='name,scheduled_at')throw Error('Clinical disclosure to leader')
 if((await api('/clinical/patients')).status!==403)throw Error('Leader accessed patients')
 if((await api('/demo/channel')).status!==403)throw Error('Leader accessed channel')
 console.log('PASS leader: only authorized name/time, no clinical or channel access')
 await logout();await login('Trabalhador')
 await api('/demo/channel/reply','POST',{patient_id:Number(carla.id),choice:'confirm',leader_consent:false})
 await logout();await login('Líder de setor')
 const revoked=await api('/demo/leader')
 if(revoked.body.data.authorized.length!==0)throw Error('Revoked consent remains visible')
 await go('/security');await wait('document.querySelector("h1")?.textContent.includes("Quem pode")')
 if(errors.length)throw Error(errors.join('\n'))
 console.log('PASS authorization withdrawal, security screen; no browser exceptions')
}finally{if(ws)ws.close();chrome.kill();await Promise.race([once(chrome,'exit'),pause(1000)]);if(path.resolve(profile).startsWith(path.resolve(cache)+path.sep))await rm(profile,{recursive:true,force:true,maxRetries:3,retryDelay:200})}
