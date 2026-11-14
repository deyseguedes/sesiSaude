export const shareDemo = import.meta.env?.VITE_SHARE_DEMO === 'true'
let dataset
const key = 'viva-elo-demo-profile'
async function data() {
  if (!dataset) dataset = fetch('/demo-data.json').then(async response => {
    if (!response.ok) throw Error('Demonstração indisponível. Atualize a página.')
    return response.json()
  })
  return dataset
}
export async function demoRequest(url, options = {}) {
  const parsed = new URL(url, location.origin), endpoint = parsed.pathname.replace(/^\/api\/v1/, '')
  const method = options.method || 'GET', snapshots = await data()
  const role = sessionStorage.getItem(key), profile = snapshots.profiles[role]
  const wrap = value => ({ data: structuredClone(value), error: null })
  if (endpoint === '/auth/session') return wrap({ user: profile?.user || null, csrf_token: 'fictional-demo' })
  if (endpoint === '/auth/demo' && method === 'POST') {
    const selected = JSON.parse(options.body).role
    if (!Object.hasOwn(snapshots.profiles, selected)) throw Error('Escolha um dos perfis disponíveis.')
    sessionStorage.setItem(key, selected)
    return wrap({ user: snapshots.profiles[selected].user, csrf_token: 'fictional-demo' })
  }
  if (endpoint === '/auth/logout' && method === 'POST') { sessionStorage.removeItem(key); return wrap({ user: null, csrf_token: 'fictional-demo' }) }
  if (!profile) throw Error('Escolha um perfil para explorar a demonstração.')
  if (method !== 'GET') throw Error('Esta demonstração é somente para consulta. Nenhuma alteração ou agendamento é gravado.')
  const lookup = endpoint === '/availability' ? `/availability/${parsed.searchParams.get('patient_id')}/${parsed.searchParams.get('modality')}${parsed.searchParams.get('my_team') === '1' ? '/team' : ''}` : endpoint
  const result = profile.routes[lookup]
  if (!result) throw Error('Este detalhe não está disponível na demonstração compartilhada.')
  if (result.status !== 200 || result.body.error) throw Error(result.body.error?.message || 'Acesso indisponível para este perfil.')
  const payload = structuredClone(result.body)
  if (endpoint === '/availability' && Array.isArray(payload.data)) {
    const modality = parsed.searchParams.get('modality')
    if (modality) payload.data = payload.data.filter(slot => slot.modality === modality)
    const profession = parsed.searchParams.get('professional_role')
    if (profession && profession !== 'all') payload.data = payload.data.filter(slot => slot.role === profession)
    if (parsed.searchParams.get('sort') === 'distance') payload.data.sort((a, b) => (a.distance_km ?? Infinity) - (b.distance_km ?? Infinity))
  }
  return payload
}
