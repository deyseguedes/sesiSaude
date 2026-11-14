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
  if (endpoint === '/management/gamification' && parsed.searchParams.get('company_id') && Number(parsed.searchParams.get('company_id')) !== Number(payload.data.company_id)) throw Error('Esta empresa não está disponível no cenário de demonstração.')
  if (endpoint === '/nursing/worklist') {
    const status = parsed.searchParams.get('status') || 'active'
    const deadline = parsed.searchParams.get('deadline')
    const day = payload.data.generated_at.slice(0, 10)
    const horizon = new Date(day + 'T12:00:00-03:00'); horizon.setUTCDate(horizon.getUTCDate() + 7)
    const lastDay = horizon.toISOString().slice(0, 10)
    payload.data.items = payload.data.items.filter(item => {
      if (status === 'active' && item.status === 'resolved') return false
      if (!['active', 'all'].includes(status) && item.status !== status) return false
      if (parsed.searchParams.get('help_requested') === '1' && !Number(item.help_requested)) return false
      for (const field of ['team_id', 'professional_id', 'service_unit_id', 'assigned_to']) if (parsed.searchParams.get(field) && Number(item[field]) !== Number(parsed.searchParams.get(field))) return false
      if (deadline === 'overdue' && !item.overdue) return false
      if (deadline === 'today' && item.due_at.slice(0, 10) !== day) return false
      if (deadline === 'next7' && (item.due_at < payload.data.generated_at || item.due_at.slice(0, 10) > lastDay)) return false
      return true
    })
    payload.data.metrics = { total: payload.data.items.length, overdue: payload.data.items.filter(i => i.overdue).length, help: payload.data.items.filter(i => i.status !== 'resolved' && Number(i.help_requested)).length }
  }
  if (endpoint === '/availability' && Array.isArray(payload.data)) {
    const modality = parsed.searchParams.get('modality')
    if (modality) payload.data = payload.data.filter(slot => slot.modality === modality)
    const profession = parsed.searchParams.get('professional_role')
    if (profession && profession !== 'all') payload.data = payload.data.filter(slot => slot.role === profession)
    if (parsed.searchParams.get('sort') === 'distance') payload.data.sort((a, b) => (a.distance_km ?? Infinity) - (b.distance_km ?? Infinity))
  }
  return payload
}
