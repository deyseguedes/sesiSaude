import { afterEach, mock, test } from 'node:test'
import assert from 'node:assert/strict'
import { requestJson } from '../src/services/http.js'

afterEach(() => mock.restoreAll())
const respond = (body, status = 200) => mock.method(globalThis, 'fetch', async () => new Response(body, { status }))

test('reads the standard API contract and preserves cookie and CSRF options', async () => {
  const fetch = respond(JSON.stringify({ data: { status: 'ok' }, error: null }))
  assert.deepEqual(await requestJson('/api/v1/health', { headers: { 'X-CSRF-Token': 'test-token' } }), { data: { status: 'ok' }, error: null })
  assert.equal(fetch.mock.calls[0].arguments[1].credentials, 'same-origin')
  assert.equal(fetch.mock.calls[0].arguments[1].headers['X-CSRF-Token'], 'test-token')
})

test('handles an empty proxy response without exposing a JSON parsing error', async () => {
  respond('', 500)
  await assert.rejects(requestJson('/api/v1/portal'), { name: 'Error', message: 'Serviço temporariamente indisponível. Tente novamente em instantes.' })
})

test('does not expose an HTML error page as a message', async () => {
  respond('<h1>Private server diagnostics</h1>', 502)
  await assert.rejects(requestJson('/api/v1/portal'), error => error.name === 'Error' && !error.message.includes('Private') && !error.message.includes('JSON'))
})

test('preserves the backend error message', async () => {
  respond(JSON.stringify({ data: null, error: { code: 'INVALID_CREDENTIALS', message: 'Credenciais inválidas.' } }), 401)
  await assert.rejects(requestJson('/api/v1/auth/login'), { message: 'Credenciais inválidas.' })
})

test('handles the structured unavailable response from the development proxy', async () => {
  respond(JSON.stringify({ data: null, error: { code: 'API_UNAVAILABLE', message: 'Serviço temporariamente indisponível.' } }), 503)
  await assert.rejects(requestJson('/api/v1/auth/logout'), { message: 'Serviço temporariamente indisponível.' })
})

for (const body of ['', 'null', '[]', '{}', '{"data":']) {
  test(`rejects an invalid successful response: ${JSON.stringify(body)}`, async () => {
    respond(body)
    await assert.rejects(requestJson('/api/v1/health'), { name: 'Error', message: 'O serviço enviou uma resposta inválida. Tente novamente.' })
  })
}

test('handles a network failure', async () => {
  mock.method(globalThis, 'fetch', async () => { throw new TypeError('Failed to fetch') })
  await assert.rejects(requestJson('/api/v1/auth/session'), { name: 'Error', message: 'Serviço temporariamente indisponível. Tente novamente em instantes.' })
})

test('handles a response interrupted while reading its body', async () => {
  mock.method(globalThis, 'fetch', async () => ({ text: async () => { throw new TypeError('terminated') } }))
  await assert.rejects(requestJson('/api/v1/portal'), { message: 'Serviço temporariamente indisponível. Tente novamente em instantes.' })
})
