<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { apiGet, apiRequest } from '../services/api.js'
import { useAuthStore } from '../stores/auth.js'
import { shareDemo } from '../services/share-demo.js'
import { filterKey, restoreFilters, nextPending } from '../utils/worklist.js'
import { useLatestQuery } from '../composables/useLatestQuery.js'
import { label, today } from '../utils.js'
import { formatDate, formatDateTime } from '../utils/workflow.js'
import SidePanel from '../components/SidePanel.vue'
import FormPanel from '../components/FormPanel.vue'
import RecordText from '../components/RecordText.vue'
import AppIcon from '../components/AppIcon.vue'
const auth = useAuthStore()
const query = useLatestQuery((options, filters) => apiGet('/nursing/worklist?' + new URLSearchParams(filters), options))
const { data, loading, error: loadError } = query
const detailQuery = useLatestQuery((options, id) => apiGet(`/nursing/worklist/${id}`, options))
const { data: selected, loading: detailLoading, error: detailError } = detailQuery
const storageKey = filterKey(auth.user)
let remembered = null
try { remembered = sessionStorage.getItem(storageKey) } catch {}
const filter = ref(restoreFilters(remembered))
const moreActions = ref(false), lastChannel = ref('')
try { const saved = sessionStorage.getItem('elo-contact-channel:' + auth.user.id); if (['phone', 'in_person', 'other'].includes(saved)) lastChannel.value = saved } catch {}
function quickFilter(preset) {
  const next = { ...filter.value, status: 'active', assigned_to: '', deadline: '', help_requested: '', contact_state: '' }
  if (preset === 'mine') next.assigned_to = String(auth.user.id)
  if (preset === 'overdue') next.deadline = 'overdue'
  if (preset === 'today') next.deadline = 'today'
  if (preset === 'lost') next.contact_state = 'lost'
  if (preset === 'help') next.help_requested = '1'
  filter.value = next
}

const error = ref(''), notice = ref(''), busy = ref(false), initialized = ref(false), search = ref(''), selectedId = ref(null), action = ref(''), creating = ref(false), createInitial = ref({}), taskPatient = ref(''), taskProfile = ref(null)
const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase()
const items = computed(() => (data.value?.items || []).filter(i => normalize(i.patient_name + ' ' + i.title).includes(normalize(search.value))))
const contactLabels = { phone: 'Ligação', in_person: 'Presencial', other: 'Outro canal', reached: 'Contato realizado', no_answer: 'Sem resposta', invalid_contact: 'Contato inválido' }
const names = { take: 'Caso assumido', open: 'Aberta', in_progress: 'Em acompanhamento', resolved: 'Finalizada', identified: 'Identificada', source_updated: 'Origem atualizada', source_resolved: 'Origem resolvida', created: 'Tarefa criada', contact: 'Contato registrado', assign: 'Responsável atribuído', start: 'Acompanhamento iniciado', resolve: 'Finalizada com justificativa', reopen: 'Reaberta', attendance: 'Comparecimento registrado', confirm: 'Retorno confirmado', reschedule: 'Reagendado', difficulty: 'Dificuldade registrada', refer_doctor: 'Encaminhamento ao médico', nursing_record: 'Acompanhamento de enfermagem' }
const returnAppointments = computed(() => (selected.value?.appointments||[]).filter(a=>a.professional_role==='doctor' && ['expected','confirmed'].includes(a.status) && a.scheduled_at.slice(0,10)>selected.value?.summary.clinical_return?.assessment_date && new Date(a.scheduled_at.replace(' ','T')+'-03:00').getTime()<=Date.now()))
const actions = computed(() => selected.value?.status === 'resolved' ? [{ id: 'reopen', title: 'Reabrir pendência' }] : [
  { id: 'contact', title: 'Registrar contato' }, { id: 'assign', title: 'Atribuir responsável' },
  ...(selected.value?.source_type === 'case' ? [{ id: 'confirm', title: 'Confirmar retorno' }, { id: 'attendance', title: 'Confirmar comparecimento' }, { id: 'reschedule', title: 'Reagendar retorno' }, { id: 'difficulty', title: 'Registrar dificuldade' }] : []),
  ...(selected.value?.source_type==='clinical_return'?[{id:'schedule_return',title:'Agendar retorno'},...(returnAppointments.value.length?[{id:'record_return',title:'Registrar comparecimento'}]:[])]:[]),
  { id: 'refer_doctor', title: 'Encaminhar ao médico' },
  ...(auth.user.role === 'nurse' && selected.value?.summary.plans.focus?.status !== 'closed' && selected.value?.summary.plans.focus ? [{ id: 'nursing_record', title: 'Registrar enfermagem' }] : []),
  { id: 'start', title: 'Marcar em acompanhamento' }, ...(selected.value?.source_type==='clinical_return'?[]:[{ id: 'resolve', title: 'Finalizar pendência' }]),
])
const otherActions = computed(() => actions.value.filter(a => !['contact','confirm','reschedule','resolve','reopen','schedule_return','record_return'].includes(a.id)))
const lastContact = computed(() => selected.value?.history.find(e => e.event_type === 'contact'))
const professionals = computed(() => selected.value?.professionals || [])
const choices = entries => entries.map(([value, title]) => ({ value, label: title }))
const field = (name, title, rest = {}) => ({ name, label: title, ...rest })
const fields = computed(() => [
  ...(action.value === 'contact' ? [field('channel', 'Como foi o contato?', { default: lastChannel.value, options: choices([['phone', 'Ligação'], ['in_person', 'Presencial'], ['other', 'Outro canal registrado']]) }), field('outcome', 'Resultado', { options: choices([['reached', 'Contato realizado'], ['no_answer', 'Sem resposta'], ['invalid_contact', 'Contato inválido']]) })] : []),
  ...(action.value === 'assign' ? [field('assigned_to', 'Profissional vinculado', { options: professionals.value.filter(u => !selected.value.team_id || Number(u.team_id) === Number(selected.value.team_id)).filter((u, i, a) => a.findIndex(v => v.id === u.id) === i).map(u => ({ value: Number(u.id), label: u.name })) })] : []),
  ...(action.value === 'record_return' ? [field('appointment_id','Consulta médica realizada',{options:returnAppointments.value.map(a=>({value:Number(a.id),label:formatDateTime(a.scheduled_at)+' · '+a.professional_name}))})] : []),
  ...(action.value === 'schedule_return' ? [field('doctor_team','Médico e equipe',{options:professionals.value.filter(u=>u.role==='doctor' && Number(u.team_id)===Number(selected.value.team_id)).map(u=>({value:u.id+':'+u.team_id,label:u.name}))}),field('date','Data do agendamento',{type:'date',default:selected.value.summary.clinical_return.due_date>=today()?selected.value.summary.clinical_return.due_date:today()}),field('time','Horário',{type:'time'})] : []),
  ...(action.value === 'reschedule' ? [field('date', 'Nova data', { type: 'date', default: today() }), field('time', 'Horário', { type: 'time' })] : []),
  ...(action.value === 'difficulty' ? [field('barrier', 'Motivo voluntário', { options: ['transport', 'schedule', 'contact', 'personal', 'declined'].map(value => ({ value, label: label(value) })), default: 'declined' })] : []),
  ...(action.value === 'refer_doctor' ? [field('doctor_team', 'Médico e equipe vinculados', { options: professionals.value.filter(u => u.role === 'doctor').map(u => ({ value: `${u.id}:${u.team_id}`, label: u.name + ' · equipe vinculada' })) })] : []),
  field('note', action.value === 'nursing_record' ? 'Registro de enfermagem' : action.value === 'resolve' ? 'Justificativa da finalização' : 'Registro do acompanhamento', { type: 'textarea', max: 1000, wide: true, optional: !['resolve', 'reopen', 'contact', 'refer_doctor', 'nursing_record'].includes(action.value), help: action.value === 'nursing_record' || action.value === 'refer_doctor' ? 'Registro restrito à equipe assistencial vinculada.' : 'Evite informações clínicas neste registro administrativo.' }),
])
const taskFields = computed(() => [field('kind', 'Tipo de pendência', { default: 'manual_task', options: choices([['manual_task', 'Tarefa de acompanhamento'], ['reschedule_request', 'Solicitação de reagendamento'], ['communication_failure', 'Falha de comunicação'], ['welcome_request', 'Solicitação de acolhimento']]) }), field('title', 'Próximo passo', { max: 180 }), field('due_date', 'Prazo de resolução', { type: 'date', default: today() }), field('professional_team', 'Responsável e equipe', { options: (taskProfile.value?.professionals || []).filter(u => taskProfile.value.teams.some(t => Number(t.id) === Number(u.team_id))).map(u => ({ value: `${u.id}:${u.team_id}`, label: u.name + ' · ' + taskProfile.value.teams.find(t => Number(t.id) === Number(u.team_id))?.name })) }), field('note', 'Orientação administrativa', { type: 'textarea', optional: true, max: 1000, wide: true })])
async function load() { return query.run({ ...filter.value }) }
async function refresh() {
  if (busy.value) return
  busy.value = true; error.value = ''
  try { if (!shareDemo) await apiRequest('/nursing/worklist/sync', 'POST'); await load() } catch (e) { error.value = e.message } finally { busy.value = false }
}
async function open(item, intent = '') { detailQuery.cancel(true); selectedId.value = item.id; action.value = ''; moreActions.value = false; error.value = ''; const result = await detailQuery.run(item.id); if (result && Number(selectedId.value) === Number(item.id)) action.value = intent }
function close() { if (busy.value) return; selectedId.value = null; action.value = ''; detailQuery.cancel(true) }
async function direct(item, type) {
  if (busy.value || shareDemo) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    await apiRequest('/nursing/worklist/' + item.id + '/actions', 'POST', { type, version: Number(item.version) })
    if (type === 'confirm') await apiRequest('/nursing/worklist/sync', 'POST')
    await load()
    if (Number(selectedId.value) === Number(item.id)) await detailQuery.run(item.id)
    notice.value = type === 'take' ? 'Caso atribuído a você e em acompanhamento.' : type === 'confirm' ? 'Retorno confirmado. Comparecimento permanece separado.' : 'Caso em acompanhamento.'
  } catch (e) { error.value = e.message } finally { busy.value = false }
}
function chooseAction(type) {
  moreActions.value = false
  if (['take', 'confirm', 'start'].includes(type)) return direct(selected.value, type)
  action.value = type
}
async function submit(values, advance = false) {
  if (busy.value || shareDemo) return
  const currentId = selectedId.value, previousIds = items.value.map(i => i.id), kind = action.value
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const body = { ...values, type: kind, version: Number(selected.value.version) }
    if (body.doctor_team) { [body.professional_id, body.team_id] = body.doctor_team.split(':').map(Number); delete body.doctor_team }
    if (kind === 'record_return') body.appointment_id=Number(body.appointment_id)
    if (kind === 'assign') body.assigned_to = Number(body.assigned_to)
    if (kind === 'nursing_record') body.plan_id = Number(selected.value.summary.plans.focus.id)
    await apiRequest('/nursing/worklist/' + currentId + '/actions', 'POST', body)
    if (kind === 'contact') { try { sessionStorage.setItem('elo-contact-channel:' + auth.user.id, body.channel) } catch {} lastChannel.value = body.channel }
    if (['confirm', 'attendance', 'reschedule', 'difficulty', 'refer_doctor','schedule_return','record_return'].includes(kind)) await apiRequest('/nursing/worklist/sync', 'POST')
    action.value = ''; notice.value = 'Ação salva com autoria e histórico.'
    if (!await load()) throw new Error(loadError.value || 'Registro salvo. Atualize a fila para continuar.')
    if (advance) {
      const next = nextPending(previousIds, currentId, items.value)
      if (next) await open({ id: next })
      else { selectedId.value = null; detailQuery.cancel(true); notice.value = 'Registro salvo. Não há outra pendência neste filtro.' }
    } else await detailQuery.run(currentId)
  } catch (e) { error.value = e.message } finally { busy.value = false }
}
async function newTask() { const patient = selected.value?.patient_id || ''; taskPatient.value = ''; createInitial.value = {}; taskProfile.value = null; creating.value = true; await nextTick(); taskPatient.value = patient }
const taskQuery = useLatestQuery((options, id) => apiGet(`/clinical/patients/${id}`, options))
watch(taskPatient, async id => { taskProfile.value = null; taskQuery.cancel(true); if (id) { await taskQuery.run(id); taskProfile.value = taskQuery.data.value; const own = (taskProfile.value?.professionals || []).filter(u => Number(u.id) === Number(auth.user.id) && taskProfile.value.teams.some(t => Number(t.id) === Number(u.team_id))); if (own.length === 1) createInitial.value = { professional_team: own[0].id + ':' + own[0].team_id }; if (taskQuery.error.value) error.value = taskQuery.error.value } })
async function createTask(values) {
  busy.value = true; error.value = ''
  try { const [assigned_to, team_id] = values.professional_team.split(':').map(Number); await apiRequest('/nursing/worklist/tasks', 'POST', { ...values, patient_id: Number(taskPatient.value), assigned_to, team_id }); creating.value = false; await load(); if (selectedId.value) await detailQuery.run(selectedId.value); notice.value = 'Tarefa criada e incluída na fila.' } catch (e) { error.value = e.message } finally { busy.value = false }
}
watch(filter, () => { try { sessionStorage.setItem(storageKey, JSON.stringify(filter.value)) } catch {} if (initialized.value) load() }, { deep: true })
onMounted(async () => { await refresh(); initialized.value = true })
</script>
<template>
  <div class="section-heading flex-wrap gap-3 mb-4"><div><p class="eyebrow">CENTRAL DE CONTINUIDADE · ENFERMAGEM</p><h1>Fila do dia</h1><p class="text-secondary mb-0">Quem precisa de acompanhamento e qual é o próximo passo.</p></div><div class="d-flex flex-wrap gap-2"><RouterLink to="/care-dashboard" class="btn btn-outline-success">Indicadores assistenciais</RouterLink><button class="btn btn-outline-success" :disabled="busy || loading" @click="refresh"><AppIcon name="cycle" :size="17" /> Atualizar fila</button><button class="btn btn-success" :disabled="busy || !data" @click="newTask"><AppIcon name="plus" :size="17" /> Criar tarefa</button></div></div>
  <p v-if="error || loadError" class="alert alert-danger" role="alert">{{ error || loadError }}</p><p v-if="notice && !selectedId" class="alert alert-success">{{ notice }}</p>
  <p class="privacy-note">Prioridade administrativa por prazo, pedido de ajuda e tempo de espera. Não representa risco clínico.</p>
  <div v-if="data" class="task-strip worklist-metrics mb-4"><div><strong>{{ data.metrics.total }}</strong><span>pendências no filtro</span></div><div><strong>{{ data.metrics.overdue }}</strong><span>com prazo passado</span></div><div><strong>{{ data.metrics.help }}</strong><span>pedidos de apoio</span></div></div>
  <section class="card card-body mb-4"><div><label for="queue-search" class="form-label">Buscar pessoa ou pendência</label><input id="queue-search" v-model="search" type="search" class="form-control" placeholder="Digite um nome"></div><div class="filter-chips mt-3" aria-label="Atalhos da fila">
<button class="btn btn-sm" :class="!filter.assigned_to && !filter.deadline && !filter.help_requested && !filter.contact_state && filter.status === 'active' ? 'btn-success' : 'btn-outline-success'" :disabled="busy" @click="quickFilter('all')">Todas abertas</button>
<button class="btn btn-sm" :class="Number(filter.assigned_to) === Number(auth.user.id) ? 'btn-success' : 'btn-outline-success'" :disabled="busy" @click="quickFilter('mine')">Minhas</button>
<button class="btn btn-sm" :class="filter.deadline === 'overdue' ? 'btn-success' : 'btn-outline-success'" :disabled="busy" @click="quickFilter('overdue')">Prazo passado</button>
<button class="btn btn-sm" :class="filter.help_requested === '1' ? 'btn-success' : 'btn-outline-success'" :disabled="busy" @click="quickFilter('help')">Pedidos de apoio</button>
<button class="btn btn-sm" :class="filter.deadline==='today'?'btn-success':'btn-outline-success'" :disabled="busy" @click="quickFilter('today')">Devidos hoje</button><button class="btn btn-sm" :class="filter.contact_state==='lost'?'btn-success':'btn-outline-success'" :disabled="busy" @click="quickFilter('lost')">Contato perdido</button>
</div><details class="mt-3"><summary>Mais filtros</summary><div class="worklist-filters mt-3"><div><label for="queue-team" class="form-label">Equipe APS</label><select id="queue-team" v-model="filter.team_id" class="form-select"><option value="">Todas</option><option v-for="t in data?.filters.teams" :key="t.id" :value="t.id">{{ t.name }}</option></select></div><div><label for="queue-professional" class="form-label">Profissional de origem</label><select id="queue-professional" v-model="filter.professional_id" class="form-select"><option value="">Todos</option><option v-for="p in data?.filters.professionals" :key="p.id" :value="p.id">{{ p.name }}</option></select></div><div><label for="queue-unit" class="form-label">Unidade de atendimento</label><select id="queue-unit" v-model="filter.service_unit_id" class="form-select"><option value="">Todas / não informada</option><option v-for="u in data?.filters.units" :key="u.id" :value="u.id">{{ u.name }}</option></select></div><div><label for="queue-deadline" class="form-label">Prazo</label><select id="queue-deadline" v-model="filter.deadline" class="form-select"><option value="">Todos</option><option value="overdue">Prazo passado</option><option value="today">Hoje</option><option value="next7">Próximos sete dias</option></select></div><div><label for="queue-status" class="form-label">Situação</label><select id="queue-status" v-model="filter.status" class="form-select"><option value="active">Abertas e em acompanhamento</option><option value="open">Abertas</option><option value="in_progress">Em acompanhamento</option><option value="resolved">Finalizadas</option><option value="all">Todas</option></select></div></div><label class="check-row mt-3 mb-0"><input v-model="filter.assigned_to" class="form-check-input" type="checkbox" :true-value="String(auth.user.id)" false-value=""><span>Atribuídas a mim</span></label></details></section>
  <p v-if="loading || busy" role="status" class="small text-secondary">Atualizando os registros…</p>
  <section class="card card-body"><div class="section-heading mb-3"><h2 class="h5 mb-0">Próximos passos</h2><small class="text-secondary">{{ items.length }} resultado(s)</small></div><div v-if="loading && !data" class="loading-state">Consultando a fila…</div><div v-for="item in items" :key="item.id" class="worklist-row" :data-item-id="item.id" :data-source="item.source_type"><div class="avatar">{{ item.patient_name[0] }}</div><div class="worklist-person"><button class="btn btn-link p-0 text-start fw-semibold" :disabled="busy" @click="open(item)">{{ item.patient_name }}</button><small>{{ item.team_name || 'Equipe não especificada na origem' }} · {{ item.service_unit || 'Unidade não informada' }}</small><small>{{ item.professional_name || 'Responsável de origem não informado' }}</small></div><div class="worklist-reason"><span class="status-badge" :class="item.status">{{ names[item.status] }}</span><strong class="d-block mt-2">{{ item.title }}</strong><p class="small mb-1">{{ item.reason }}</p><small>{{ item.next_action }}</small><details class="small mt-1"><summary>Por que aparece nesta posição?</summary>{{ item.priority_reason }}</details></div><div class="worklist-deadline"><small>Prazo previsto</small><strong :class="{ 'text-warning-emphasis': item.overdue }">{{ formatDateTime(item.due_at) }}</strong><small>Próximo retorno: {{ item.next_return_at ? formatDateTime(item.next_return_at) : 'Sem programação' }}</small><small v-if="item.last_contact_outcome">Último contato: {{ contactLabels[item.last_contact_outcome] }}</small><small>{{ item.assigned_name ? 'Atribuída a ' + item.assigned_name : 'Sem atribuição' }}</small><div class="d-flex flex-wrap gap-2 mt-2">
<button v-if="item.status !== 'resolved'" class="btn btn-sm btn-success" :disabled="busy || shareDemo" :title="shareDemo ? 'Somente consulta na demonstração' : 'Abrir registro de contato'" @click="open(item, 'contact')">Registrar contato</button>
<button v-if="item.can_take && !(Number(item.assigned_to) === Number(auth.user.id) && item.status === 'in_progress')" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="direct(item, 'take')">Assumir caso</button>
<button v-if="item.can_confirm" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="direct(item, 'confirm')">Confirmar retorno</button>
</div></div></div><p v-if="data && !items.length" class="empty-state mb-0">Nenhuma pendência para este filtro.</p></section>
  <SidePanel :data-followup-id="selectedId" :open="!!selectedId && !creating" title="Ficha de acompanhamento" :subtitle="selected?.summary.patient.name" :busy="busy" @close="close"><p v-if="detailLoading" role="status">Carregando ficha…</p><p v-if="error || detailError" class="alert alert-danger" role="alert">{{ error || detailError }}</p><template v-if="selected"><p v-if="notice" class="alert alert-success" role="status">{{ notice }}</p><p><span class="status-badge">{{ names[selected.status] }}</span> · {{ selected.title }}</p><p class="small">{{ selected.reason }}</p><p v-if="lastContact" class="small text-secondary">Último contato: {{ contactLabels[lastContact.payload.channel] }} · {{ contactLabels[lastContact.payload.outcome] }} · {{ lastContact.author_name }} · {{ formatDateTime(lastContact.created_at) }}</p>
<div class="d-flex flex-wrap gap-2 mb-4">
  <button v-if="selected.status !== 'resolved'" class="btn btn-sm btn-success" :disabled="busy || shareDemo" @click="chooseAction('contact')">Registrar contato</button>
  <button v-if="selected.can_take && !(Number(selected.assigned_to) === Number(auth.user.id) && selected.status === 'in_progress')" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="chooseAction('take')">Assumir caso</button>
  <button v-if="selected.can_confirm" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="chooseAction('confirm')">Confirmar retorno</button>
  <button v-else-if="selected.source_type === 'case' && selected.status !== 'resolved'" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="chooseAction('reschedule')">Reagendar retorno</button>
  <button v-if="selected.status !== 'resolved' && selected.source_type !== 'clinical_return'" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="chooseAction('resolve')">Finalizar pendência</button>
  <button v-if="selected.source_type === 'clinical_return' && selected.status !== 'resolved'" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="chooseAction('schedule_return')">Agendar retorno</button><button v-if="selected.source_type === 'clinical_return' && selected.status !== 'resolved' && returnAppointments.length" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="chooseAction('record_return')">Registrar comparecimento</button><button v-if="selected.status === 'resolved'" class="btn btn-sm btn-outline-success" :disabled="busy || shareDemo" @click="chooseAction('reopen')">Reabrir pendência</button>
  <details v-if="selected.status !== 'resolved'" class="worklist-more" :open="moreActions" @toggle="moreActions = $event.target.open">
    <summary class="btn btn-sm btn-outline-secondary">Outras ações</summary>
    <div class="worklist-more-menu">
      <button v-for="a in otherActions" :key="a.id" class="btn btn-sm btn-link text-start" :disabled="busy || shareDemo" @click="chooseAction(a.id)">{{ a.title }}</button>
      <button class="btn btn-sm btn-link text-start" :disabled="busy || shareDemo" @click="moreActions = false; newTask()">Criar tarefa</button>
    </div>
  </details>
</div>
<p v-if="shareDemo" class="demo-disclosure small">Demonstração somente para consulta. As ações de gravação estão desabilitadas antes do preenchimento.</p>
<section v-if="action" class="card card-body mb-4"><h3 class="h6">{{ actions.find(a => a.id === action)?.title }}</h3><p v-if="action === 'attendance'" class="small text-secondary">Registre somente comparecimento verificado. Um retorno confirmado não comprova atendimento.</p><FormPanel :key="`${selected.id}-${action}-${selected.version}`" :fields="fields" :busy="busy" submit-label="Salvar acompanhamento" submit-next-label="Salvar e abrir próxima" @submit="submit" @submit-next="values => submit(values, true)" /></section><p v-if="selected.summary.clinical_return?.available" class="alert alert-light border mt-3"><strong>Risco confirmado: {{ selected.summary.clinical_return.risk }}</strong> · retorno devido {{ formatDate(selected.summary.clinical_return.due_date) }}<br><small>{{ selected.summary.clinical_return.doctor_name }} · {{ selected.summary.clinical_return.team_name }}</small></p><div class="summary-facts worklist-facts"><div><span>Próximo retorno</span><strong>{{ selected.summary.next_appointment ? formatDateTime(selected.summary.next_appointment.scheduled_at) : 'Sem retorno programado' }}</strong></div><div><span>Último comparecimento registrado</span><strong>{{ selected.summary.last_attended ? formatDateTime(selected.summary.last_attended.scheduled_at) : 'Sem registro' }}</strong></div></div><section class="mt-4"><h3 class="h6">Plano de cuidado em destaque</h3><template v-if="selected.summary.plans.focus"><p class="small text-secondary">{{ label(selected.summary.plans.focus.status) }} · {{ selected.summary.plans.focus.professional_name }} · reavaliação {{ formatDate(selected.summary.plans.focus.reassessment_date) }}</p><RecordText :text="selected.summary.plans.focus.objectives" /><h4 class="summary-label mt-3">Ações e intervenções registradas</h4><RecordText :text="selected.summary.plans.focus.interventions" /></template><p v-else class="text-secondary">Nenhum plano registrado.</p><RouterLink :to="`/patients/${selected.patient_id}`" class="btn btn-sm btn-outline-success mt-3">Abrir prontuário e metas completas →</RouterLink></section><details class="mt-4"><summary>Metas e adesão registrada</summary><div v-for="g in selected.summary.goals.items" :key="g.id" class="border-top py-2"><strong>{{ g.description }}</strong><p class="small mb-0">{{ label(g.progress.state) }} · prazo {{ formatDate(g.deadline) }} · origem {{ label(g.indicator?.measurement_source) }}</p></div><p v-if="!selected.summary.goals.total" class="small mt-2">Nenhuma meta ativa registrada.</p><p v-if="selected.summary.latest_adherence" class="small mt-3">Última ação: {{ selected.summary.latest_adherence.action_description }} · {{ Number(selected.summary.latest_adherence.completed) ? 'Registrada como realizada' : 'Registrada como não realizada' }} · {{ label(selected.summary.latest_adherence.source) }} · {{ formatDateTime(selected.summary.latest_adherence.occurred_at) }}</p><p v-else class="small mt-3">Sem registro de adesão.</p></details><details class="mt-4"><summary>Consultas e comparecimentos</summary><p v-for="a in selected.appointments" :key="a.id" class="small border-top py-2 mb-0">{{ formatDateTime(a.scheduled_at) }} · {{ label(a.status) }} · {{ a.professional_name }}</p></details><details class="mt-4"><summary>Barreiras e histórico de acolhimento</summary><div v-for="c in selected.cases" :key="c.id" class="border-top py-2"><p class="small">Retorno {{ c.id }} · {{ c.barrier ? label(c.barrier) : 'Sem barreira informada' }} · {{ c.barrier_resolved ? 'Resolução registrada' : 'Sem resolução registrada' }}</p><div v-for="e in c.contacts" :key="e.id" class="small mb-3"><strong>{{ label(e.action_type) }}</strong> · {{ formatDateTime(e.created_at) }}<RecordText :text="e.administrative_note" /></div></div></details><details class="mt-4"><summary>Encaminhamentos e próximas atividades</summary><p v-for="r in selected.summary.recent_referrals" :key="r.id" class="small border-top py-2">{{ r.recipient_name }} · {{ label(r.status) }} · {{ formatDateTime(r.created_at) }}</p><p v-for="a in selected.next_activities" :key="a.id" class="small border-top py-2">{{ a.title }} · {{ formatDateTime(a.due_at) }} · {{ names[a.status] }}</p></details><details class="mt-4" open><summary>Histórico desta pendência</summary><div v-for="e in selected.history" :key="e.id" class="border-top py-3"><strong class="small">{{ names[e.event_type] || e.event_type }}</strong><p class="summary-authorship mb-2">{{ e.author_name }} · {{ formatDateTime(e.created_at) }}</p><p v-if="e.payload.channel" class="small mb-2">{{ contactLabels[e.payload.channel] }} ? {{ contactLabels[e.payload.outcome] }}</p><RecordText :text="e.note" :limit="200" /></div></details></template></SidePanel>
  <SidePanel :open="creating" title="Criar próximo passo" :busy="busy" @close="creating = false"><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><label for="task-patient" class="form-label">Pessoa acompanhada</label><select id="task-patient" v-model="taskPatient" class="form-select mb-4" :disabled="busy"><option value="">Escolha uma pessoa vinculada</option><option v-for="p in data?.patients" :key="p.id" :value="p.id">{{ p.name }}</option></select><FormPanel v-if="taskProfile" :key="taskPatient" :fields="taskFields" :initial="createInitial" :busy="busy" submit-label="Criar tarefa" @submit="createTask" /></SidePanel>
</template>
