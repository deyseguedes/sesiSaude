import test from 'node:test'
import assert from 'node:assert/strict'
import { demoRequest, shareDemo } from '../src/services/share-demo.js'

test('public demo is opt-in and keeps profile selection separate from real authentication', async () => {
  assert.equal(shareDemo, false)
  const values = new Map(), requests = []
  globalThis.location = { origin: 'https://demo.example.test' }
  globalThis.sessionStorage = { getItem: k => values.get(k), setItem: (k, v) => values.set(k, v), removeItem: k => values.delete(k) }
  const routes = { '/clinical/patients': { status: 200, body: { data: [{ id: 1, name: 'Fictícia' }], error: null } } }
  globalThis.fetch = async url => { requests.push(url); return { ok: true, json: async () => ({ profiles: { doctor: { user: { id: 1, role: 'doctor' }, routes }, manager: { user: { id: 2, role: 'manager' }, routes: { '/clinical/patients': { status: 403, body: { data: null, error: { message: 'Acesso restrito.' } } } } } } }) } }
  try {
    assert.equal((await demoRequest('/api/v1/auth/session')).data.user, null)
    await assert.rejects(demoRequest('/api/v1/clinical/patients'), /Escolha um perfil/)
    await assert.rejects(demoRequest('/api/v1/auth/demo', { method: 'POST', body: '{"role":"invalid"}' }), /Escolha um dos perfis/)
    await demoRequest('/api/v1/auth/demo', { method: 'POST', body: '{"role":"doctor"}' })
    assert.equal((await demoRequest('/api/v1/auth/session')).data.user.role, 'doctor')
    const patients = await demoRequest('/api/v1/clinical/patients'); patients.data[0].name = 'Changed'
    assert.equal((await demoRequest('/api/v1/clinical/patients')).data[0].name, 'Fictícia')
    await assert.rejects(demoRequest('/api/v1/care-plans', { method: 'POST' }), /somente para consulta/)
    await demoRequest('/api/v1/auth/demo', { method: 'POST', body: '{"role":"manager"}' })
    await assert.rejects(demoRequest('/api/v1/clinical/patients'), /Acesso restrito/)
    await demoRequest('/api/v1/auth/logout', { method: 'POST' })
    assert.equal((await demoRequest('/api/v1/auth/session')).data.user, null)
    assert.deepEqual(requests, ['/demo-data.json'])
  } finally { delete globalThis.location; delete globalThis.sessionStorage; delete globalThis.fetch }
})
