import { onScopeDispose, ref } from 'vue'

export function useLatestQuery(fetcher, initial = null) {
  const data = ref(initial), error = ref(''), loading = ref(false)
  let sequence = 0, controller
  function cancel(clear = false) {
    sequence++; controller?.abort(); loading.value = false
    if (clear) { data.value = initial; error.value = '' }
  }
  async function run(...args) {
    const current = ++sequence
    controller?.abort(); controller = new AbortController()
    loading.value = true; error.value = ''
    try {
      const result = await fetcher({ signal: controller.signal }, ...args)
      if (current === sequence) data.value = result
      return current === sequence ? result : undefined
    } catch (failure) {
      if (current === sequence && failure.name !== 'AbortError') error.value = failure.message
    } finally {
      if (current === sequence) loading.value = false
    }
  }
  onScopeDispose(() => cancel())
  return { data, error, loading, run, cancel }
}
