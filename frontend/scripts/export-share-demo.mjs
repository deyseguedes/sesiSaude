// The only source is a freshly created fictional QA database, never the application database.
import { spawn, spawnSync } from 'node:child_process'
import { once } from 'node:events'
import { createServer } from 'node:net'
import { mkdir, writeFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import path from 'node:path'
const root = fileURLToPath(new URL('..', import.meta.url)), backend = path.resolve(root, '../backend')
const php = process.env.PHP_BINARY || 'php'
function fixture(input) {
  const result = spawnSync(php, ['tests/browser-fixture.php'], { cwd: backend, input: JSON.stringify(input), encoding: 'utf8', windowsHide: true })
  if (result.status !== 0) throw Error('Não foi possível preparar o banco fictício isolado.')
  return JSON.parse(result.stdout)
}
let setup, server
try {
  setup = fixture({ action: 'create', nursing_scenario: true })
  const listener = createServer().listen(0, '127.0.0.1'); await once(listener, 'listening')
  const port = listener.address().port; await new Promise(resolve => listener.close(resolve))
  const origin = `http://127.0.0.1:${port}`
  server = spawn(php, ['-S', `127.0.0.1:${port}`, '-t', 'public', 'public/router.php'], { cwd: backend, env: { ...process.env, ...setup.env }, stdio: 'ignore', windowsHide: true })
  let ready = false
  for (let i = 0; i < 100; i++) { try { ready = (await fetch(origin + '/api/v1/health')).ok } catch {} if (ready) break; await new Promise(r => setTimeout(r, 100)) }
  if (!ready) throw Error('API fictícia indisponível.')
  const snapshot = { generated_at: new Date().toISOString(), profiles: {} }
  for (const role of ['admin', 'manager', 'doctor', 'nurse', 'navigator', 'worker', 'dependent']) {
    let cookie = ''
    async function request(endpoint, options = {}) {
      const response = await fetch(origin + '/api/v1' + endpoint, { ...options, headers: { Cookie: cookie, ...options.headers } })
      const cookies = response.headers.getSetCookie(); if (cookies.length) cookie = cookies.map(c => c.split(';')[0]).join('; ')
      return { status: response.status, body: await response.json() }
    }
    const session = await request('/auth/session')
    const login = await request('/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': session.body.data.csrf_token }, body: JSON.stringify({ email: `${role}@demo.vivaelo.test`, password: setup.password }) })
    if (login.status !== 200) throw Error('Falha no perfil fictício ' + role)
    const profile = { user: login.body.data.user, routes: {} }; snapshot.profiles[role] = profile
    async function capture(endpoint) {
      const result = await request(endpoint); profile.routes[endpoint] = result
      return result.status === 200 ? result.body.data : null
    }
    for (const endpoint of ['/portal', '/management/companies', '/management/gamification', '/management/overview', '/management/slots', '/management/campaigns', '/management/contents', '/clinical/dashboard', '/nursing/dashboard', '/continuity/dashboard', '/continuity/cases', '/engagement/pipeline', '/me/beneficiaries', '/me/appointments', '/me/campaigns', '/me/contents', '/professional/appointments', '/availability']) await capture(endpoint)
    if (role === 'worker') {
      const synced = await request('/me/journey/sync', { method:'POST', headers:{ 'X-CSRF-Token':login.body.data.csrf_token } })
      if (synced.status !== 200) throw Error('Fictional worker journey initialization failed')
    }
    await capture('/me/journey')
    if (role === 'doctor') {
      const synced = await request('/professional/reactivation/sync', { method:'POST', headers:{ 'X-CSRF-Token':login.body.data.csrf_token } })
      if (synced.status !== 200) throw Error('Fictional doctor recognition initialization failed')
      await capture('/professional/reactivation')
    }
    const patients = await capture('/clinical/patients')
    if (['doctor','nurse'].includes(role)) {
      const synced = await request('/nursing/worklist/sync', { method: 'POST', headers: { 'X-CSRF-Token': login.body.data.csrf_token } })
      if (synced.status !== 200) throw Error('Falha ao preparar fila fictícia.')
      const queue = await request('/nursing/worklist?status=all')
      profile.routes['/nursing/worklist'] = queue
      for (const item of queue.body.data.items) await capture(`/nursing/worklist/${item.id}`)
    }
    const beneficiaries = profile.routes['/me/beneficiaries']?.body.data
    for (const beneficiary of Array.isArray(beneficiaries) ? beneficiaries : []) {
      for (const mode of ['in_person', 'telehealth']) {
        const endpoint = `/availability?patient_id=${beneficiary.id}&modality=${mode}&near=industry&professional_role=all`
        const result = await request(endpoint)
        profile.routes[`/availability/${beneficiary.id}/${mode}`] = result
        profile.routes[`/availability/${beneficiary.id}/${mode}/team`] = await request(endpoint + '&my_team=1')
      }
    }
    for (const p of patients || []) {
      await capture(`/clinical/patients/${p.id}`); await capture(`/clinical/patients/${p.id}/summary`)
      const indicators = await capture(`/clinical/patients/${p.id}/indicators`)
      for (const i of indicators || []) await capture(`/indicators/${i.id}/measurements`)
    }
    const plans = await capture('/care-plans')
    for (const p of plans || []) { await capture(`/care-plans/${p.id}`); await capture(`/care-plans/${p.id}/goals`) }
    for (const [endpoint, detail] of [['/continuity/cases', 'continuity/cases'], ['/engagement/pipeline', 'engagement/participants']]) {
      const rows = profile.routes[endpoint]?.body.data
      for (const row of Array.isArray(rows) ? rows : []) await capture(`/${detail}/${row.id}` + (detail === 'engagement/participants' ? '/assignees' : ''))
    }
    await request('/auth/logout', { method: 'POST', headers: { 'X-CSRF-Token': login.body.data.csrf_token } })
    console.log('Exportado perfil fictício: ' + role)
  }
  const serialized = JSON.stringify(snapshot, (key, value) => /password|csrf|token|secret/i.test(key) ? undefined : value)
  await mkdir(path.join(root, 'public'), { recursive: true })
  await writeFile(path.join(root, 'public/demo-data.json'), serialized)
  console.log('Exportação concluída. Banco temporário será removido.')
} finally {
  if (server && server.exitCode === null) { server.kill(); await Promise.race([once(server, 'exit'), new Promise(r => setTimeout(r, 2000))]) }
  if (setup) fixture({ action: 'drop', database: setup.database })
}
