<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { apiGet, apiRequest } from '../services/api.js'
import { label, options, today } from '../utils.js'
import FormPanel from '../components/FormPanel.vue'
import SidePanel from '../components/SidePanel.vue'
import { formatDateTime } from '../utils/workflow.js'
const route = useRoute()
const data = ref(null), selected = ref(null), error = ref(''), notice = ref(''), busy = ref(false), type = ref('welcome')
const filter = ref({ from: today().slice(0, 7) + '-01', to: today(), patient_id: '' }), status = ref('')
const types = options(['confirm', 'attendance', 'barrier', 'welcome', 'assign_navigator', 'reschedule', 'reconnect', 'resolve_barrier', 'clinical_review'])
const fields = computed(() => [
  ...(type.value === 'barrier' ? [{ name: 'barrier', label: 'Barreira informada voluntariamente', options: options(['transport', 'schedule', 'contact', 'personal', 'declined']) }] : []),
  ...(type.value === 'assign_navigator' ? [{ name: 'navigator_id', label: 'Navegador vinculado', options: (selected.value?.navigators || []).map(u => ({ value: Number(u.id), label: u.name })) }] : []),
  ...(type.value === 'reschedule' ? [{ name: 'date', label: 'Nova data', type: 'date' }, { name: 'time', label: 'Horário', type: 'time' }] : []),
  { name: 'administrative_note', label: 'Registro administrativo de acolhimento', type: 'textarea', optional: true, max: 1000, wide: true, help: 'Não inclua diagnósticos, resultados de exames ou detalhes clínicos. Solicite revisão pela APS quando necessário.' },
])
const metrics = { returns_expected: 'Retornos previstos', returns_confirmed: 'Retornos confirmados', pending: 'Pendências', rescheduled: 'Reagendamentos', reconnected: 'Reconexões', barriers_resolved: 'Barreiras resolvidas', continuity_rate: 'Taxa de continuidade' }
const cases = computed(() => data.value?.cases.filter(c => !status.value || c.status === status.value) || [])
async function load() { data.value = await apiGet('/continuity/dashboard?' + new URLSearchParams(filter.value)) }
async function refresh(sync = false) {
  busy.value = true; error.value = ''; selected.value = null
  try { if (sync) { const result = await apiRequest('/continuity/sync', 'POST'); notice.value = `${result.created} pendência(s) criada(s).` } await load() }
  catch (e) { data.value = null; error.value = e.message } finally { busy.value = false }
}
async function select(id) { busy.value = true; error.value = ''; try { selected.value = await apiGet(`/continuity/cases/${id}`) } catch (e) { selected.value = null; error.value = e.message } finally { busy.value = false } }
async function submit(form) {
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const id = selected.value.id
    await apiRequest(`/continuity/cases/${id}/actions`, 'POST', { ...form, type: type.value, version: Number(selected.value.version) })
    await load(); selected.value = await apiGet(`/continuity/cases/${id}`); notice.value = 'Ação registrada. Equipe APS informada do resultado administrativo.'
  } catch (e) { error.value = e.message } finally { busy.value = false }
}
async function quickConfirm(c) {
  busy.value = true; error.value = ''
  try { await apiRequest(`/continuity/cases/${c.id}/actions`, 'POST', { type: 'confirm', version: Number(c.version) }); await load(); notice.value = 'Retorno confirmado. Equipe APS informada.' } catch (e) { error.value = e.message } finally { busy.value = false }
}
let filterTimer
watch(() => [filter.value.from, filter.value.to], () => { clearTimeout(filterTimer); if (filter.value.from && filter.value.to && filter.value.from <= filter.value.to) filterTimer = setTimeout(() => refresh(), 250) })
onBeforeUnmount(() => clearTimeout(filterTimer))
onMounted(async () => { await refresh(); if (route.query.case && data.value?.cases.some(c => Number(c.id) === Number(route.query.case))) await select(Number(route.query.case)) })
</script>
<template>
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4"><div><p class="eyebrow">ELO RECONECTA</p><h1>O cuidado continua no vínculo</h1><p class="text-secondary">Retornos, barreiras voluntárias e acolhimento humano.</p></div><button class="btn btn-success" :disabled="busy" @click="refresh(true)">Identificar retornos não confirmados</button></div>
  <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p v-if="notice" class="alert alert-success" role="status">{{ notice }}</p>
  <form class="card card-body mb-4" @submit.prevent="refresh()"><div class="row align-items-end g-3"><div class="col-md-4"><label for="continuity-from" class="form-label">De</label><input id="continuity-from" v-model="filter.from" type="date" class="form-control" required></div><div class="col-md-4"><label for="continuity-to" class="form-label">Até</label><input id="continuity-to" v-model="filter.to" type="date" class="form-control" required></div><div class="col-md-4 small text-secondary">O período é atualizado automaticamente.</div></div></form>
  <p v-if="busy" role="status">Processando…</p>
  <template v-if="data"><div class="row g-3 mb-4"><div v-for="(value, key) in data.metrics" :key="key" class="col-6 col-lg-3"><div class="card card-body h-100"><span class="small text-secondary">{{ metrics[key] }}</span><strong class="display-6">{{ value === null ? '—' : `${value}${key === 'continuity_rate' ? '%' : ''}` }}</strong></div></div></div>
    <div class="row g-4"><section class="col-12"><div class="card card-body"><h2 class="h5">Casos de continuidade</h2><label for="case-status" class="form-label">Filtrar situação</label><select id="case-status" v-model="status" class="form-select mb-3"><option value="">Todas</option><option v-for="s in options(['pending', 'confirmed', 'attended', 'rescheduled', 'reconnected'])" :key="s.value" :value="s.value">{{ s.label }}</option></select><div class="table-responsive"><table class="table"><thead><tr><th>Paciente</th><th>Retorno</th><th>Situação</th><th>Ações</th></tr></thead><tbody><tr v-for="c in cases" :key="c.id"><td>{{ c.patient_name }}</td><td>{{ formatDateTime(c.scheduled_at) }}</td><td>{{ label(c.status) }}</td><td><div class="d-flex gap-2"><button v-if="c.status === 'pending'" class="btn btn-sm btn-success" :disabled="busy" @click="quickConfirm(c)">Confirmar retorno</button><button class="btn btn-sm btn-outline-success" :disabled="busy" @click="type = 'welcome'; select(c.id)">Acolher</button><button v-if="['pending', 'confirmed'].includes(c.status)" class="btn btn-sm btn-outline-secondary" :disabled="busy" @click="type = 'reschedule'; select(c.id)">Reagendar</button></div></td></tr><tr v-if="!cases.length"><td colspan="4">Nenhum caso no período. Identifique os retornos ou ajuste o filtro.</td></tr></tbody></table></div></div></section>
      <SidePanel :open="!!selected" title="Acolher e organizar o retorno" :subtitle="data?.cases.find(c => Number(c.id) === Number(selected?.id))?.patient_name" :busy="busy" @close="selected = null"><template v-if="selected"><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p class="small text-secondary">{{ label(selected.status) }} · {{ selected.barrier ? label(selected.barrier) : 'Barreira não informada' }}</p><label for="action-type" class="form-label">O que você quer registrar?</label><select id="action-type" v-model="type" class="form-select mb-4" :disabled="busy"><option v-for="o in types" :key="o.value" :value="o.value">{{ o.label }}</option></select><FormPanel :key="`${selected.id}-${type}`" :fields="fields" :busy="busy" submit-label="Registrar e avisar a equipe" @submit="submit" /><details class="mt-4"><summary>Histórico de acolhimento</summary><div v-for="a in selected.actions" :key="a.id" class="border-top py-3"><strong>{{ label(a.action_type) }}</strong><div class="small text-secondary">{{ formatDateTime(a.created_at) }}</div><p>{{ a.administrative_note }}</p></div></details></template></SidePanel>
    </div>
  </template>
</template>
