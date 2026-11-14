import { test } from 'node:test'
import assert from 'node:assert/strict'
import { effectScope } from 'vue'
import { useLatestQuery } from '../src/composables/useLatestQuery.js'

test('only the latest filter result reaches the screen', async () => {
  const scope = effectScope(), pending = []
  const query = scope.run(() => useLatestQuery((options, filter) => new Promise(resolve => pending.push({ ...options, filter, resolve })), []))
  const first = query.run('presencial'), second = query.run('teleconsulta')
  assert.equal(pending[0].signal.aborted, true)
  pending[1].resolve(['teleconsulta']); await second
  pending[0].resolve(['presencial']); await first
  assert.deepEqual(query.data.value, ['teleconsulta'])
  assert.equal(query.loading.value, false)
  scope.stop()
})
test('a stale error cannot replace a successful newer response', async () => {
  const scope = effectScope(), pending = []
  const query = scope.run(() => useLatestQuery(() => new Promise((resolve, reject) => pending.push({ resolve, reject }))))
  const first = query.run(), second = query.run()
  pending[1].resolve('novo'); await second
  pending[0].reject(new Error('antigo')); await first
  assert.equal(query.error.value, '')
  assert.equal(query.data.value, 'novo')
  scope.stop()
})
test('cancel clears pending results and unmount aborts the request', async () => {
  const scope = effectScope(); let resolve, signal
  const query = scope.run(() => useLatestQuery(options => { signal = options.signal; return new Promise(done => { resolve = done }) }, []))
  const pending = query.run(); scope.stop()
  assert.equal(signal.aborted, true)
  resolve(['antigo']); await pending
  assert.deepEqual(query.data.value, [])
})
