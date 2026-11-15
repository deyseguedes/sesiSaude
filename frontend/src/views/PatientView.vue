<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { apiGet, apiRequest } from '../services/api.js'
import { useAuthStore } from '../stores/auth.js'
import { label, options, today, timestamp } from '../utils.js'
import FormPanel from '../components/FormPanel.vue'
import TrendChart from '../components/TrendChart.vue'
import AppIcon from '../components/AppIcon.vue'
import PatientSummary from '../components/PatientSummary.vue'
import ClinicalReturn from '../components/ClinicalReturn.vue'
import { defaultPlan, formatDate, formatDateTime } from '../utils/workflow.js'
const route = useRoute(), auth = useAuthStore()
const patient = ref(null), plans = ref([]), plan = ref(null), indicators = ref([]), measurements = ref([])
const selectedPlan = ref(''), selectedIndicator = ref(''), goalIndicator = ref(''), editingGoal = ref(null)
const error = ref(''), notice = ref(''), busy = ref(false), section = ref('overview'), creating = ref(false)
const planInitial = ref({}), goalInitial = ref({})
const summaryVersion = ref(0)
const reviewOpen = ref(false)
async function openReview() { section.value = 'records'; reviewOpen.value = true; await nextTick(); document.getElementById('plan-review')?.scrollIntoView({ block: 'start' }) }
async function openSummarySource(target) {
  if (busy.value) return
  if (target.planId && Number(selectedPlan.value) !== Number(target.planId)) await selectPlan(Number(target.planId))
  if (target.indicatorId) selectedIndicator.value = Number(target.indicatorId)
  section.value = target.section
}
const field = (name, label, extra = {}) => ({ name, label, ...extra })
const professionals = computed(() => patient.value?.professionals.map(u => ({ value: Number(u.id), label: `${u.name} · equipe #${u.team_id}` })) || [])
const teamOptions = computed(() => patient.value?.teams.map(t => ({ value: Number(t.id), label: t.name })) || [])
const selected = computed(() => indicators.value.find(i => Number(i.id) === Number(selectedIndicator.value)))
const selectedGoalIndicator = computed(() => indicators.value.find(i => Number(i.id) === Number(goalIndicator.value)))
const planFields = computed(() => [field('professional_id', 'Profissional responsável', { options: professionals.value }), field('team_id', 'Equipe APS', { options: teamOptions.value }), field('start_date', 'Início', { type: 'date', default: today() }), field('reassessment_date', 'Reavaliação prevista', { type: 'date' }), field('status', 'Situação', { options: options(['active', 'paused', 'reviewed', 'closed']), default: 'active' }), field('objectives', 'Objetivos terapêuticos', { type: 'textarea', wide: true }), field('interventions', 'Intervenções planejadas', { type: 'textarea', wide: true }), field('clinical_notes', 'Observações clínicas restritas', { type: 'textarea', optional: true, wide: true, max: 8000 })])
const indicatorFields = [field('name', 'Nome', { max: 150 }), field('type', 'Tipo', { options: options(['numeric', 'percentage', 'categorical', 'frequency', 'range', 'adherence', 'attendance', 'continuity']) }), field('unit', 'Unidade', { max: 40 }), field('reference_value', 'Referência configurada pelo profissional', { optional: true }), field('measurement_method', 'Método de medição', { max: 200 }), field('frequency', 'Frequência', { max: 100 }), field('categories_text', 'Categorias, separadas por ponto e vírgula', { optional: true, help: 'Obrigatório para indicadores categóricos. Ex.: categoria A; categoria B' })]
const goalFields = computed(() => {
  const categorical = selectedGoalIndicator.value?.type === 'categorical'
  return [field('description', 'Descrição da meta', { type: 'textarea', max: 1000, wide: true }), field('initial_value', 'Valor inicial', { type: categorical ? 'text' : 'number', optional: true }), field('target_value', 'Valor-alvo', categorical ? { options: options(selectedGoalIndicator.value?.categories || []) } : { type: 'number', optional: true, help: 'Para faixa-alvo, preencha os limites abaixo.' }), field('target_min', 'Limite inferior da faixa', { type: 'number', optional: true }), field('target_max', 'Limite superior da faixa', { type: 'number', optional: true }), field('direction', 'Direção esperada', { options: options(categorical ? ['category'] : ['increase', 'decrease', 'range', 'maintain']) }), field('measurement_source', 'Origem usada na avaliação', { options: options(['professional', 'self_reported']), default: 'professional' }), field('min_samples', 'Mínimo de medições para avaliar', { type: 'number', min: 1, default: 1 }), field('frequency', 'Frequência de acompanhamento', { max: 100 }), field('deadline', 'Prazo', { type: 'date' }), field('responsible_id', 'Responsável', { options: professionals.value }), field('status', 'Situação', { options: options(['active', 'paused', 'cancelled']), default: 'active' })]
})
const measurementFields = computed(() => [field('value', 'Valor coletado', selected.value?.type === 'categorical' ? { options: options(selected.value.categories) } : { type: 'number' }), field('source', 'Origem da informação', { options: options(['professional', 'self_reported']), default: 'professional' }), field('collected_at', 'Data e hora da coleta', { type: 'datetime-local', default: timestamp().slice(0, 16).replace(' ', 'T') }), field('method', 'Método utilizado', { default: selected.value?.measurement_method, max: 200 })])
const reviewFields = [field('notes', 'Registro da revisão', { type: 'textarea', max: 8000, wide: true }), field('outcome', 'Resultado', { options: options(['active', 'reviewed', 'closed']) }), field('reassessment_date', 'Próxima reavaliação', { type: 'date' })]
const evolutionFields = computed(() => [field('kind', 'Tipo do registro', { options: options(auth.user.role === 'nurse' ? ['nursing', 'vital_signs', 'education'] : ['clinical']) }), field('notes', 'Evolução registrada pelo profissional', { type: 'textarea', wide: true, max: 8000 })])
const adherenceFields = [field('action_description', 'Ação pactuada'), field('completed', 'Ação realizada', { type: 'checkbox' }), field('source', 'Origem da informação', { options: options(['professional', 'self_reported']) }), field('occurred_at', 'Data e hora', { type: 'datetime-local', default: timestamp().slice(0, 16).replace(' ', 'T') })]
const referralFields = computed(() => [field('team_id', 'Equipe de destino', { options: teamOptions.value }), field('to_professional_id', 'Profissional de destino', { options: professionals.value }), field('reason', 'Motivo clínico restrito', { type: 'textarea', wide: true })])
const appointmentFields = computed(() => [field('team_id', 'Equipe APS', { options: teamOptions.value }), field('professional_id', 'Profissional', { options: professionals.value }), field('date', 'Data do retorno', { type: 'date' }), field('time', 'Horário', { type: 'time' })])
const sqlTime = value => value.replace('T', ' ') + (value.length === 16 ? ':00' : '')
function eventSummary(event) {
  try {
    const payload = JSON.parse(event.payload)
    if (event.event_type.startsWith('goal_')) {
      const g = payload.after
      return `${g.description} · ${label(g.direction)} · alvo ${g.target_value ?? `${g.target_min} a ${g.target_max}`} ${g.unit} · prazo ${g.deadline}`
    }
    if (event.event_type === 'plan_updated') return `Objetivos: ${payload.after.objectives} · Situação: ${label(payload.after.status)} · Reavaliação: ${payload.after.reassessment_date}`
    return payload.outcome ? label(payload.outcome) : ''
  } catch { return '' }
}
async function load() {
  const id = Number(route.params.id)
  const [profile, allPlans, patientIndicators] = await Promise.all([apiGet(`/clinical/patients/${id}`), apiGet('/care-plans'), apiGet(`/clinical/patients/${id}/indicators`)])
  if (Number(route.params.id) !== id) return
  patient.value = profile
  plans.value = allPlans.filter(p => Number(p.patient_id) === id)
  indicators.value = patientIndicators
  if (!selectedPlan.value && !creating.value) selectedPlan.value = defaultPlan(plans.value, route.query.plan)?.id || ''
  if (selectedPlan.value) {
    const planId = selectedPlan.value
    const detail = await apiGet(`/care-plans/${planId}`)
    if (Number(route.params.id) !== id || Number(selectedPlan.value) !== Number(planId)) return
    plan.value = detail
    planInitial.value = { ...plan.value, professional_id: Number(plan.value.professional_id), team_id: Number(plan.value.team_id) }
  } else plan.value = null
  if (!indicators.value.some(i => Number(i.id) === Number(selectedIndicator.value))) selectedIndicator.value = indicators.value[0]?.id || ''
  await loadMeasurements()
}
async function loadMeasurements() {
  const indicator = selectedIndicator.value, patientId = route.params.id
  measurements.value = []
  const rows = indicator ? await apiGet(`/indicators/${indicator}/measurements`) : []
  if (String(selectedIndicator.value) === String(indicator) && route.params.id === patientId) measurements.value = rows
}
async function selectPlan(id) { selectedPlan.value = id; creating.value = false; editingGoal.value = null; error.value = ''; busy.value = true; try { await load() } catch (e) { plan.value = null; error.value = e.message } finally { busy.value = false } }
function newPlan() { selectedPlan.value = ''; plan.value = null; creating.value = true; section.value = 'plan'; planInitial.value = { professional_id: Number(auth.user.id), team_id: Number(patient.value.teams[0]?.id || 0), start_date: today(), status: 'active' } }
async function mutate(path, method, data, after) {
  busy.value = true; error.value = ''; notice.value = ''
  try { const result = await apiRequest(path, method, data); after?.(result); await load(); summaryVersion.value++; notice.value = 'Registro salvo com autoria e histórico.' }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
function savePlan(data) {
  const editing = !!selectedPlan.value
  return mutate(editing ? `/care-plans/${selectedPlan.value}` : '/care-plans', editing ? 'PUT' : 'POST', { ...data, patient_id: Number(route.params.id), professional_id: Number(data.professional_id), team_id: Number(data.team_id), ...(editing ? { version: Number(plan.value.version) } : {}) }, result => { selectedPlan.value = result.id; creating.value = false })
}
function saveIndicator(data) { const { categories_text, ...rest } = data; return mutate('/indicators', 'POST', { ...rest, patient_id: Number(route.params.id), categories: categories_text ? categories_text.split(';').map(v => v.trim()).filter(Boolean) : [] }, result => { selectedIndicator.value = result.id }) }
function editGoal(g) { editingGoal.value = g; goalIndicator.value = g.indicator.id; goalInitial.value = { ...g, responsible_id: Number(g.responsible_id), measurement_source: g.indicator.measurement_source }; section.value = 'goals' }
function clearGoal() { editingGoal.value = null; goalInitial.value = {}; goalIndicator.value = indicators.value.length === 1 ? indicators.value[0].id : '' }
function saveGoal(data) {
  const editing = editingGoal.value
  return mutate(editing ? `/care-goals/${editing.id}` : `/care-plans/${selectedPlan.value}/goals`, editing ? 'PUT' : 'POST', { ...data, indicator_id: Number(goalIndicator.value), responsible_id: Number(data.responsible_id), initial_value: data.initial_value === '' ? null : data.initial_value, ...(editing ? { version: Number(editing.version) } : {}) }, clearGoal)
}
watch(selectedIndicator, async () => { try { await loadMeasurements() } catch (e) { error.value = e.message } })
watch(() => route.query.section, value => { if (patient.value && ['overview', 'plan', 'goals', 'indicators', 'records', 'referral'].includes(value)) section.value = value })
watch(() => route.params.id, async () => {
  reviewOpen.value = false; patient.value = null; plan.value = null; plans.value = []; indicators.value = []; measurements.value = []; editingGoal.value = null; selectedPlan.value = ''; selectedIndicator.value = ''; goalIndicator.value = ''; section.value = 'overview'; error.value = ''; notice.value = ''; creating.value = false; busy.value = true
  try { await load(); if (['overview', 'plan', 'goals', 'indicators', 'records', 'referral'].includes(route.query.section)) section.value = route.query.section; if (indicators.value.length === 1) goalIndicator.value = indicators.value[0].id }
  catch (e) { patient.value = null; error.value = e.message } finally { busy.value = false }
}, { immediate: true })
</script>
<template>
  <RouterLink to="/patients" class="small">← Pacientes acompanhados</RouterLink>
  <p v-if="error" class="alert alert-danger mt-3" role="alert">{{ error }}</p><p v-if="notice" class="alert alert-success mt-3" role="status">{{ notice }}</p><p v-if="busy" role="status">Processando…</p>
  <template v-if="patient">
    <div class="d-flex justify-content-between flex-wrap gap-3 my-4"><div><p class="eyebrow">ACOMPANHAMENTO INDIVIDUAL</p><h1>{{ patient.name }}</h1><p class="text-secondary">Equipe APS · acesso assistencial restrito</p></div><button class="btn btn-success align-self-start" :disabled="busy" @click="newPlan">Criar plano de cuidado</button></div>
    <ClinicalReturn :patient-id="Number(patient.id)" :teams="patient.teams" @saved="summaryVersion++" />
    <PatientSummary :patient-id="Number(patient.id)" :refresh-key="summaryVersion" :compact="true" :busy="busy" @navigate="openSummarySource" />
    <div class="clinical-quick-actions mb-4" :data-guide-patient-section="section"><button class="btn btn-success" :disabled="busy" @click="section = 'records'"><AppIcon name="plus" :size="17" /> Registrar evolução</button><button class="btn btn-outline-success" :disabled="busy" @click="section = 'indicators'">Registrar medição</button><button v-if="plan && plan.status !== 'closed'" class="btn btn-outline-success" :disabled="busy" @click="openReview">Reavaliar plano</button><button class="btn btn-outline-success" :disabled="busy" @click="section = 'goals'">Acompanhar metas</button><button class="btn btn-outline-secondary" :disabled="busy" @click="section = 'overview'">Programar retorno</button></div><div class="row g-4"><aside class="col-lg-3"><div class="card card-body"><h2 class="h6">Planos de cuidado</h2><p v-if="!plans.length" class="small text-secondary">Nenhum plano registrado.</p><button v-for="p in plans" :key="p.id" class="btn text-start my-1 care-plan-option" :class="Number(selectedPlan) === Number(p.id) ? 'btn-success' : 'btn-outline-secondary'" :disabled="busy" @click="selectPlan(p.id)">Plano de cuidado<small>{{ label(p.status) }} · {{ formatDate(p.reassessment_date) }}</small></button></div></aside>
      <div class="col-lg-9"><nav class="nav nav-pills gap-1 mb-4" aria-label="Seções do paciente"><button v-for="tab in [{ id: 'overview', title: 'Histórico' }, { id: 'plan', title: 'Plano' }, { id: 'goals', title: 'Metas' }, { id: 'indicators', title: 'Indicadores' }, { id: 'records', title: 'Registros e revisão' }, { id: 'referral', title: 'Encaminhar' }]" :key="tab.id" class="nav-link" :class="{ active: section === tab.id }" :disabled="busy" @click="section = tab.id">{{ tab.title }}</button></nav>
        <section v-if="section === 'overview'" class="card card-body"><h2 class="h4">Histórico de consultas e retornos</h2><p v-if="!patient.appointments.length">Nenhum atendimento registrado.</p><p v-for="a in patient.appointments" :key="a.id">{{ formatDateTime(a.scheduled_at) }} · {{ label(a.status) }} · profissional #{{ a.professional_id }}</p><RouterLink to="/continuity">Acompanhar continuidade no ELO Reconecta →</RouterLink><h3 class="h5 mt-4">Programar retorno</h3><FormPanel :fields="appointmentFields" :busy="busy" @submit="d => mutate(`/clinical/patients/${patient.id}/appointments`, 'POST', { ...d, team_id: Number(d.team_id), professional_id: Number(d.professional_id) })" /></section>
        <section v-if="section === 'plan'" class="card card-body"><h2 class="h4">{{ creating ? 'Novo plano individualizado' : plan ? `Plano #${plan.id}` : 'Selecione ou crie um plano' }}</h2><p v-if="plan" class="small text-secondary">Criado em {{ plan.created_at }} · versão {{ plan.version }}</p><FormPanel v-if="plan || creating" :fields="planFields" :initial="planInitial" :busy="busy" @submit="savePlan" /></section>
        <section v-if="section === 'goals'" class="card card-body"><h2 class="h4">Metas e indicadores</h2><p v-if="!plan">Selecione um plano para definir metas.</p><template v-else><div v-for="g in plan.goals" :key="g.id" class="border-bottom py-3"><div class="d-flex justify-content-between gap-3"><strong>{{ g.description }}</strong><button class="btn btn-sm btn-outline-secondary" :disabled="busy" @click="editGoal(g)">Ajustar meta</button></div><p class="mb-1">{{ g.indicator?.name }} · {{ label(g.progress.state) }} · {{ g.progress.samples }}/{{ g.min_samples }} medições mínimas</p><small>{{ label(g.direction) }} · alvo {{ g.target_value ?? `${g.target_min} a ${g.target_max}` }} {{ g.unit }} · prazo {{ g.deadline }} · origem {{ label(g.indicator?.measurement_source) }}</small></div><h3 class="h5 mt-4">{{ editingGoal ? 'Revisar meta' : 'Nova meta pactuada' }}</h3><button v-if="editingGoal" class="btn btn-link text-start" @click="clearGoal">Cancelar edição</button><label for="goal-indicator" class="form-label">Indicador associado</label><select id="goal-indicator" v-model="goalIndicator" class="form-select mb-3" :disabled="busy"><option value="">Selecione um indicador</option><option v-for="i in indicators" :key="i.id" :value="i.id">{{ i.name }} · {{ i.unit }}</option></select><FormPanel v-if="goalIndicator" :key="`${editingGoal?.id || 'new'}-${goalIndicator}`" :fields="goalFields" :initial="goalInitial" :busy="busy" @submit="saveGoal" /><p v-else class="small text-secondary">Cadastre um indicador na seção Indicadores e selecione-o acima.</p></template></section>
        <section v-if="section === 'indicators'"><div class="card card-body mb-4"><h2 class="h4">Medições e evolução temporal</h2><label for="measure-indicator" class="form-label">Indicador</label><select id="measure-indicator" v-model="selectedIndicator" class="form-select mb-3"><option value="">Selecione</option><option v-for="i in indicators" :key="i.id" :value="i.id">{{ i.name }} · {{ i.unit }}</option></select><template v-if="selected"><TrendChart :indicator="selected" :measurements="measurements" /><div class="table-responsive mt-3"><table class="table small"><thead><tr><th>Coleta</th><th>Valor</th><th>Origem</th><th>Método</th><th>Autoria</th></tr></thead><tbody><tr v-for="m in measurements" :key="m.id"><td>{{ m.collected_at }}</td><td>{{ m.value_num ?? m.value_category }} {{ selected.unit }}</td><td>{{ label(m.source) }}</td><td>{{ m.method }}</td><td>{{ m.professional_id ? `Profissional #${m.professional_id}` : `Autorrelato, registrado por #${m.recorded_by}` }}</td></tr></tbody></table></div><h3 class="h5 mt-3">Registrar medição</h3><FormPanel :key="selectedIndicator" :fields="measurementFields" :busy="busy" @submit="d => mutate(`/indicators/${selectedIndicator}/measurements`, 'POST', { ...d, collected_at: sqlTime(d.collected_at) })" /></template></div><details class="card card-body"><summary class="h5">Configurar novo indicador</summary><div class="pt-3"><FormPanel :fields="indicatorFields" :busy="busy" @submit="saveIndicator" /></div></details></section>
        <section v-if="section === 'records'"><p v-if="!plan">Selecione um plano para registrar acompanhamento.</p><template v-else><div class="card card-body mb-4"><h2 class="h4">Evolução profissional</h2><FormPanel :fields="evolutionFields" :busy="busy" @submit="d => mutate(`/care-plans/${plan.id}/evolutions`, 'POST', d)" /><div v-for="e in plan.evolutions" :key="e.id" class="border-top mt-3 pt-3"><strong>{{ label(e.kind) }}</strong> · {{ e.created_at }} · profissional #{{ e.author_id }}<p>{{ e.notes }}</p></div></div><details class="card card-body mb-4"><summary class="h5">Ações pactuadas e adesão</summary><div class="pt-3"><FormPanel :fields="adherenceFields" :busy="busy" @submit="d => mutate(`/care-plans/${plan.id}/adherence`, 'POST', { ...d, occurred_at: sqlTime(d.occurred_at) })" /><p v-for="a in plan.adherence" :key="a.id" class="mt-3">{{ a.action_description }} · {{ Number(a.completed) ? 'Realizada' : 'Não realizada' }} · {{ label(a.source) }}</p></div></details><details id="plan-review" class="card card-body mb-4" :open="reviewOpen" @toggle="reviewOpen = $event.target.open"><summary class="h5">Reavaliar ou encerrar plano</summary><div class="pt-3"><FormPanel :fields="reviewFields" :busy="busy" @submit="d => mutate(`/care-plans/${plan.id}/reviews`, 'POST', { ...d, version: Number(plan.version) })" /><div v-for="r in plan.reviews" :key="r.id" class="border-top mt-3 pt-3">{{ r.created_at }} · {{ label(r.outcome) }} · profissional #{{ r.author_id }}<p>{{ r.notes }}</p></div></div></details><details class="card card-body"><summary class="h5">Histórico de alterações</summary><p v-for="e in plan.events" :key="e.id" class="small mt-3">{{ e.created_at }} · {{ label(e.event_type) }} · profissional #{{ e.author_id }}<br>{{ eventSummary(e) }}</p></details></template></section>
        <section v-if="section === 'referral'" class="card card-body"><h2 class="h4">Encaminhamento para profissional vinculado</h2><p class="small text-secondary">O motivo clínico fica restrito à equipe habilitada.</p><FormPanel :fields="referralFields" :busy="busy" @submit="d => mutate('/clinical/referrals', 'POST', { ...d, patient_id: Number(patient.id), team_id: Number(d.team_id), to_professional_id: Number(d.to_professional_id) })" /></section>
      </div>
    </div>
  </template>
</template>
