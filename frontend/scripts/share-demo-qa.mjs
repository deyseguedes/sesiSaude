import { spawn } from 'node:child_process'
import { once } from 'node:events'
import { mkdir, mkdtemp, writeFile, rm } from 'node:fs/promises'
import { createServer as netServer } from 'node:net'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
import assert from 'node:assert/strict'
import { createServer } from 'vite'
const root = fileURLToPath(new URL('..', import.meta.url))
const pause = ms => new Promise(r => setTimeout(r, ms))
let vite, chrome, socket, profile
try {
  vite = await createServer({ configFile: false, root: path.resolve(root, '../share-demo/dist'), logLevel: 'silent', server: { host: '127.0.0.1', port: 0 } }); await vite.listen()
  const origin = `http://127.0.0.1:${vite.httpServer.address().port}`
  const listener = netServer().listen(0, '127.0.0.1'); await once(listener, 'listening'); const port = listener.address().port; await new Promise(r => listener.close(r))
  await mkdir(path.join(root, 'node_modules/.cache'), { recursive: true })
  profile = await mkdtemp(path.join(root, 'node_modules/.cache/elo-share-'))
  chrome = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', ['--headless=new', '--no-first-run', '--no-default-browser-check', '--disable-extensions', `--user-data-dir=${profile}`, `--remote-debugging-port=${port}`, '--window-size=1440,1000', 'about:blank'], { stdio: 'ignore', windowsHide: true })
  let page
  for (let i = 0; i < 100 && !page; i++) { try { page = (await (await fetch(`http://127.0.0.1:${port}/json`)).json()).find(p => p.type === 'page') } catch {} if (!page) await pause(100) }
  socket = new WebSocket(page.webSocketDebuggerUrl); await once(socket, 'open')
  let id = 0; const pending = new Map(), errors = []
  socket.addEventListener('message', e => { const m = JSON.parse(e.data); if (m.method === 'Runtime.exceptionThrown') errors.push(m.params.exceptionDetails.text); if (m.id && pending.has(m.id)) { const p = pending.get(m.id); pending.delete(m.id); clearTimeout(p.timer); m.error ? p.reject(Error(m.error.message)) : p.resolve(m.result) } })
  const send = (method, params = {}) => new Promise((resolve, reject) => { const request = ++id, timer = setTimeout(() => reject(Error('Browser timeout')), 15000); pending.set(request, { resolve, reject, timer }); socket.send(JSON.stringify({ id: request, method, params })) })
  const evaluate = async expression => { const r = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true }); if (r.exceptionDetails) throw Error(r.exceptionDetails.exception?.description || r.exceptionDetails.text); return r.result?.value }
  async function wait(expression) { for (let i = 0; i < 100; i++) { if (await evaluate(expression)) return; await pause(100) } throw Error('Missing: ' + expression) }
  const click = selector => evaluate(`document.querySelector(${JSON.stringify(selector)}).click()`)
  await send('Page.enable'); await send('Runtime.enable')
  await send('Page.navigate', { url: origin + '/login' }); await wait(`document.querySelectorAll('.demo-profile').length === 7`)
  assert.equal(await evaluate(`!!document.querySelector('input[type="password"]')`), false)
  await writeFile(path.join(root, 'node_modules/.cache/demo-levels.png'), Buffer.from((await send('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  const roles = ['admin', 'manager', 'doctor', 'nurse', 'navigator', 'worker', 'dependent']
  for (let index = 0; index < roles.length; index++) {
    await evaluate(`document.querySelectorAll('.demo-profile')[${index}].click()`)
    await wait(`!!document.querySelector('.workspace-content h1') && !document.querySelector('.loading-state') && !document.querySelector('[role="status"]')`)
    assert.equal(await evaluate(`!!document.querySelector('.alert-danger')`), false, roles[index] + ' home')
    if (roles[index] === 'worker') {
      await wait(`document.querySelector('[data-testid="worker-journey"] .journey-score strong')?.textContent==='60'`)
      assert.equal(await evaluate(`document.querySelectorAll('.journey-mission.earned').length`),3)
      await writeFile(path.join(root, 'node_modules/.cache/demo-worker-journey.png'),Buffer.from((await send('Page.captureScreenshot',{format:'png'})).data,'base64'))
    }
    if (roles[index] === 'nurse') {
      await wait(`!!document.querySelector('.worklist-row')`)
      assert.ok(await evaluate(`document.querySelector('.workspace-content h1').textContent.includes('Fila do dia')`))
      assert.ok(await evaluate(`document.querySelector('.workspace-content').textContent.includes('Maria')`))
      await click('.worklist-row button'); await wait(`!!document.querySelector('dialog[open] .summary-facts')`)
      await writeFile(path.join(root, 'node_modules/.cache/demo-nursing-followup.png'), Buffer.from((await send('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
      await click('dialog[open] [aria-label="Fechar painel"]')
    }
    const links = await evaluate(`[...document.querySelectorAll('.app-sidebar a')].map(a=>a.getAttribute('href')).filter((v,i,a)=>a.indexOf(v)===i)`)
    for (const link of links) {
      await click(`.app-sidebar a[href="${link}"]`)
      await pause(400)
      await wait(`!document.querySelector('.loading-state') && !document.querySelector('[role="status"]')`)
      assert.equal(await evaluate(`!!document.querySelector('.alert-danger')`), false, roles[index] + ' ' + link)
      if (link === '/management' && ['admin','manager'].includes(roles[index])) {
        await wait(`!!document.querySelector('[data-testid="company-gamification"] button')`)
        assert.ok(await evaluate(`document.querySelector('[data-testid="company-gamification"] button').disabled`))
      }
    }
    if (roles[index] === 'doctor') {
      await click('.app-sidebar a[href="/"]')
      await wait(`document.querySelector('[data-testid="team-recognition"]')?.textContent.includes('Não apurável')`)
      assert.equal(await evaluate(`!!document.querySelector('[data-testid="doctor-reactivation"]')`),false)
      await click('.app-sidebar a[href="/patients"]'); await wait(`!!document.querySelector('a[href^="/patients/"]')`)
      await click('a[href^="/patients/"]'); await wait(`!!document.querySelector('.summary-facts')`)
      await wait(`document.querySelector('#return-risk')?.value==='3'`)
      assert.ok(await evaluate(`[...document.querySelectorAll('[data-testid="clinical-return"] input,[data-testid="clinical-return"] select,[data-testid="clinical-return"] textarea,[data-testid="clinical-return"] button.btn-success')].every(e=>e.disabled)`))
      await evaluate(`[...document.querySelectorAll('[data-testid="patient-summary"] button')].find(e=>e.textContent==='Ver resumo completo').click();true`)
      assert.ok(await evaluate(`document.querySelector('[data-testid="patient-summary"]').textContent.includes('Última evolução')`))
      await evaluate(`document.querySelector('.clinical-quick-actions button').click();true`)
      await wait(`!!document.querySelector('.workflow-form textarea')`)
      assert.ok(await evaluate(`[...document.querySelectorAll('.workflow-form textarea, .workflow-form button.btn-success')].every(e=>e.disabled)`))

      await writeFile(path.join(root, 'node_modules/.cache/demo-record.png'), Buffer.from((await send('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
    }
    await send('Page.reload'); await pause(600); await wait(`!!document.querySelector('.demo-disclosure button')`)
    await click('.demo-disclosure button'); await wait(`document.querySelectorAll('.demo-profile').length===7`)
    console.log('PASS passwordless profile, navigation, refresh and logout: ' + roles[index])
  }
  await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1, mobile: true })
  assert.ok(await evaluate(`document.documentElement.scrollWidth <= window.innerWidth`))
  await writeFile(path.join(root, 'node_modules/.cache/demo-levels-mobile.png'), Buffer.from((await send('Page.captureScreenshot', { format: 'png' })).data, 'base64'))
  assert.deepEqual(errors, [])
  console.log('PASS mobile profile selector; no browser runtime errors')
} finally {
  socket?.close()
  if (chrome && chrome.exitCode === null) { chrome.kill(); await Promise.race([once(chrome, 'exit'), pause(2000)]) }
  if (vite) await vite.close()
  if (profile && path.resolve(profile).startsWith(path.join(root, 'node_modules/.cache') + path.sep)) await rm(profile, { recursive: true, force: true })
}
