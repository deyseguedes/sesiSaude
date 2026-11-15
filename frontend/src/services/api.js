import { useAuthStore } from '../stores/auth.js'
import { requestJson } from './http.js'
import {recoverDemoSession,sessionIdentity} from './demo-session.js'
export async function apiRequest(path, method = 'GET', data, options = {}) {
  const auth = useAuthStore()
  const selected=sessionIdentity(auth)
  const send=()=>requestJson(`/api/v1${path}`, {
    method,
    headers: { Accept: 'application/json', ...(data ? { 'Content-Type': 'application/json' } : {}), ...(method !== 'GET' ? { 'X-CSRF-Token': auth.csrf } : {}) },
    credentials: 'same-origin',
    body: data ? JSON.stringify(data) : undefined,
    signal: options.signal,
  })
  let payload
  try{payload=await send()}catch(error){
    if(error.status!==401||error.code!=='UNAUTHENTICATED'||path.startsWith('/auth/')||options.signal?.aborted)throw error
    let recovered=false
    try{recovered=await recoverDemoSession(auth,selected)}catch{throw error}
    if(!recovered)throw error
    payload=await send()
  }
  return payload.data
}
export const apiGet = (path, options) => apiRequest(path, 'GET', undefined, options)
