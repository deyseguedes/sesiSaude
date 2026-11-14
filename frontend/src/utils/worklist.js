const defaults = { team_id: '', professional_id: '', service_unit_id: '', deadline: '', status: 'active', assigned_to: '', help_requested: '' }
export function filterKey(user) { return `elo-worklist-v1:${user.company_id}:${user.id}` }
export function restoreFilters(raw) {
  let saved
  try { saved=JSON.parse(raw) } catch { return { ...defaults } }
  if(!saved || typeof saved!=='object' || Array.isArray(saved)) return { ...defaults }
  const result={ ...defaults }
  for(const key of ['team_id','professional_id','service_unit_id','assigned_to']) if(/^[1-9]\d*$/.test(String(saved[key] ?? ''))) result[key]=String(saved[key])
  if(['active','open','in_progress','resolved','all'].includes(saved.status)) result.status=saved.status
  if(['','overdue','today','next7'].includes(saved.deadline)) result.deadline=saved.deadline
  if(saved.help_requested==='1') result.help_requested='1'
  return result
}
export function nextPending(previousIds, currentId, available) {
  const ids=new Set(available.map(i=>Number(i.id)))
  const start=previousIds.findIndex(id=>Number(id)===Number(currentId))
  return previousIds.slice(start+1).find(id=>Number(id)!==Number(currentId) && ids.has(Number(id)))
    ?? available.find(i=>Number(i.id)!==Number(currentId))?.id ?? null
}
