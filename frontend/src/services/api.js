import { useAuthStore } from '../stores/auth.js'
export async function apiRequest(path, method = 'GET', data) {
  const auth = useAuthStore()
  const response = await fetch(`/api/v1${path}`, {
    method,
    headers: { Accept: 'application/json', ...(data ? { 'Content-Type': 'application/json' } : {}), ...(method !== 'GET' ? { 'X-CSRF-Token': auth.csrf } : {}) },
    credentials: 'same-origin',
    body: data ? JSON.stringify(data) : undefined,
  })
  const payload = await response.json()
  if (!response.ok) throw new Error(payload.error?.message || 'Falha ao consultar a API.')
  return payload.data
}
export const apiGet = (path) => apiRequest(path)
