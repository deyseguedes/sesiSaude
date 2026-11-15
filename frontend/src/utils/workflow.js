export function normalizeSearch(value) {
  return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim()
}

export function initialFieldValue(field, initial = {}) {
  if (Object.hasOwn(initial, field.name)) return initial[field.name]
  if (Object.hasOwn(field, 'default')) return field.default
  if (field.type === 'checkbox') return false
  if (field.type === 'checkbox_group') return []
  if (!field.optional && !field.noAutoSelect && Array.isArray(field.options) && field.options.length === 1) return field.options[0].value
  return ''
}

export function defaultPlan(plans, requested) {
  return plans.find(p => Number(p.id) === Number(requested))
    || plans.find(p => p.status === 'active')
    || plans.find(p => p.status !== 'closed')
    || plans[0] || null
}

export {formatarData as formatDate,formatarDataHora as formatDateTime} from './format.js'

export function navigationFor(user) {
  if (!user) return { primary: [], secondary: [], actions: [] }
  const clinical = ['doctor', 'nurse'].includes(user.role)
  const beneficiary = ['worker', 'dependent'].includes(user.role)
  const management = ['admin','manager'].includes(user.role) && !!user.management_level && user.management_level !== 'none'
  const primary = [{ to: '/', label: 'Início', icon: 'home' }]
  const secondary = [], actions = []
  if (beneficiary) {
    primary.push({ to: '/find-care', label: 'Agendar e retornos', icon: 'calendar' }, { to: '/my-care', label: 'Meu cuidado', icon: 'book' })
    actions.push({ to: '/find-care', label: 'Encontrar atendimento', detail: 'Presencial ou teleconsulta', icon: 'calendar' })
  }
  if (clinical) {
    primary.push({ to: '/patients', label: 'Pacientes', icon: 'people' }, { to: '/my-agenda', label: 'Minha agenda', icon: 'calendar' }, { to: '/continuity', label: 'Retornos', icon: 'heart' })
    secondary.push({ to: '/engagement', label: 'Acolhimento', icon: 'message' })
    actions.push({ to: '/patients', label: 'Buscar paciente', detail: 'Abrir plano, registrar medição ou evolução', icon: 'people' }, { to: '/my-agenda', label: 'Abrir minha agenda', detail: 'Atendimentos e teleconsultas', icon: 'calendar' })
  }
  if (user.role === 'navigator') {
    primary.push({ to: '/continuity', label: 'Retornos', icon: 'heart' }, { to: '/engagement', label: 'Acolhimento', icon: 'message' })
    actions.push({ to: '/continuity', label: 'Acolher um retorno', detail: 'Confirmação, barreiras e reagendamento', icon: 'heart' })
  }
  if (management) {
    const target = clinical ? secondary : primary
    target.push({ to: '/management', label: 'Gestão', icon: 'building' }, { to: '/campaign-management', label: 'Campanhas', icon: 'cycle' })
    actions.push({ to: '/management?new=invite', label: 'Convidar trabalhador ou dependente', detail: 'Gerar link de cadastro', icon: 'people' }, { to: '/campaign-management?new=campaign', label: 'Criar campanha', detail: 'Novo ciclo de educação e cuidado', icon: 'cycle' })
    if (clinical || user.role === 'admin') actions.push({ to: '/management?new=slot', label: 'Disponibilizar horário', detail: 'Agenda presencial ou teleconsulta', icon: 'calendar' })
  }
  if (clinical || management) {
    (clinical ? secondary : primary).push({ to: '/education-editor', label: 'Conteúdos', icon: 'book' })
    actions.push({ to: '/education-editor?new=content', label: 'Preparar conteúdo educativo', detail: 'Vídeo, roteiro e revisão', icon: 'book' })
  }
  if(user.role==='nurse')secondary.push({to:'/campaign-management',label:'Campanhas e avalia??o',icon:'cycle'})
  if(['admin','nurse'].includes(user.role))secondary.push({to:'/import',label:'Importar exporta??o',icon:'building'})
  secondary.push({to:'/security',label:'Segurança e privacidade',icon:'book'})
  return { primary, secondary, actions }
}
