import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createServer } from 'vite'
import viteConfig from '../vite.config.js'

test('proxy returns a JSON 503 when the backend is unavailable', async () => {
  const server = await createServer({
    configFile: false,
    logLevel: 'silent',
    server: {
      host: '127.0.0.1',
      port: 0,
      proxy: { '/api': { ...viteConfig.server.proxy['/api'], target: 'http://127.0.0.1:1' } },
    },
  })
  try {
    await server.listen()
    const address = server.httpServer.address()
    const response = await fetch(`http://127.0.0.1:${address.port}/api/v1/health`)
    assert.equal(response.status, 503)
    assert.match(response.headers.get('content-type'), /application\/json/)
    assert.equal(response.headers.get('cache-control'), 'no-store')
    assert.deepEqual(await response.json(), {
      data: null,
      error: { code: 'API_UNAVAILABLE', message: 'Serviço temporariamente indisponível. Tente novamente em instantes.' },
    })
  } finally {
    await server.close()
  }
})
