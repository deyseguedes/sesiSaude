import { useAuthStore } from '../stores/auth.js'
import { requestJson } from './http.js'
export async function apiRequest(path, method = 'GET', data, options = {}) {
  const auth = useAuthStore()
  const payload = await requestJson(`/api/v1${path}`, {
    method,
    headers: { Accept: 'application/json', ...(data ? { 'Content-Type': 'application/json' } : {}), ...(method !== 'GET' ? { 'X-CSRF-Token': auth.csrf } : {}) },
    credentials: 'same-origin',
    body: data ? JSON.stringify(data) : undefined,
    signal: options.signal,
  })
  return payload.data
}
export const apiGet = (path, options) => apiRequest(path, 'GET', undefined, options)
