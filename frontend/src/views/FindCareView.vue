<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { apiGet, apiRequest } from '../services/api.js'
import { useAuthStore } from '../stores/auth.js'
import { useLatestQuery } from '../composables/useLatestQuery.js'
import { formatDateTime } from '../utils/workflow.js'
import { label } from '../utils.js'
import FormPanel from '../components/FormPanel.vue'
import SidePanel from '../components/SidePanel.vue'
import AppIcon from '../components/AppIcon.vue'
import AppointmentCard from '../components/AppointmentCard.vue'
const auth = useAuthStore(), route = useRoute()
const beneficiaries = ref([]), appointments = ref([]), patient = ref(''), mode = ref('in_person'), near = ref('industry'), sort = ref('earliest'), myTeam = ref(false), professionalRole = ref('doctor'), latitude = ref(null), longitude = ref(null)
const busy = ref(false), locating = ref(false), initialized = ref(false), scheduled = ref(false), error = ref(''), notice = ref(''), chosen = ref(null), authorized = ref(false), supportAppointment = ref(null), familyOpen = ref(false), email = ref(''), invitation = ref('')
const current = computed(() => beneficiaries.value.find(b => Number(b.id) === Number(patient.value)))
const query = computed(() => ({ patient_id: patient.value, modality: mode.value, near: near.value, sort: sort.value, professional_role: professionalRole.value, my_team: myTeam.value ? '1' : '0', ...(near.value === 'home' && latitude.value !== null ? { latitude: latitude.value, longitude: longitude.value } : {}) }))
const availability = useLatestQuery((options, params) => apiGet('/availability?' + new URLSearchParams(params), options), [])
const { data: slots, error: searchError, loading: searching } = availability
const locationNeeded = computed(() => mode.value === 'in_person' && sort.value === 'distance' && near.value === 'home' && latitude.value === null)
const supportFields = [{ name: 'barrier', label: 'O que está dificultando seu retorno?', options: [{ value: 'declined', label: 'Prefiro não informar' }, { value: 'schedule', label: 'Horário / rotina' }, { value: 'transport', label: 'Transporte' }, { value: 'contact', label: 'Contato' }, { value: 'personal', label: 'Questão pessoal' }], default: 'declined' }, { name: 'administrative_note', label: 'Como podemos ajudar?', type: 'textarea', optional: true, max: 1000, wide: true, help: 'Este pedido é administrativo. Não inclua resultados de exames ou diagnósticos.' }]
let timer
async function load() {
  const [people, visits] = await Promise.all([apiGet('/me/beneficiaries'), apiGet('/me/appointments')])
  beneficiaries.value = people; appointments.value = visits
  if (!people.some(p => Number(p.id) === Number(patient.value))) patient.value = people.find(p => Number(p.user_id) === Number(auth.user.id))?.id || people[0]?.id || ''
}
function scheduleSearch() {
  clearTimeout(timer); availability.cancel(true); chosen.value = null; scheduled.value = false
  if (!initialized.value || current.value?.status !== 'active' || locationNeeded.value) return
  scheduled.value = true
  timer = setTimeout(() => { scheduled.value = false; availability.run({ ...query.value }) }, 180)
}
function locate() {
  error.value = ''
  if (!navigator.geolocation) { error.value = 'Localização não disponível. Busque pela unidade industrial ou pelo primeiro horário.'; return }
  locating.value = true
  navigator.geolocation.getCurrentPosition(position => { latitude.value = position.coords.latitude; longitude.value = position.coords.longitude; near.value = 'home'; sort.value = 'distance'; locating.value = false }, () => { locating.value = false; error.value = 'Não foi possível obter sua localização. A busca pela indústria continua disponível.' }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 })
}
function choose(slot) { chosen.value = slot; authorized.value = false; error.value = '' }
async function book() {
  if (!authorized.value || !chosen.value || busy.value) return
  const selected = chosen.value
  busy.value = true; error.value = ''; notice.value = ''
  try { await apiRequest(`/availability/${selected.id}/book`, 'POST', { patient_id: Number(patient.value), version: Number(selected.version), authorize_team: true }); chosen.value = null; await load(); await availability.run({ ...query.value }); notice.value = `Atendimento confirmado para ${formatDateTime(selected.starts_at)}. Ele já aparece nos seus retornos.` }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
async function support(data) {
  busy.value = true; error.value = ''
  try { await apiRequest(`/me/appointments/${supportAppointment.value.id}/support`, 'POST', data); supportAppointment.value = null; notice.value = 'Pedido recebido. Sua equipe foi avisada para ajudar a organizar o retorno.' }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
async function inviteDependent() {
  busy.value = true; error.value = ''
  try { const result = await apiRequest(`/me/beneficiaries/${patient.value}/dependent-invitations`, 'POST', { email: email.value }); invitation.value = new URL(result.registration_path, window.location.origin).href }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
async function copyInvite() { try { await navigator.clipboard.writeText(invitation.value); notice.value = 'Convite copiado para compartilhar com o dependente.' } catch { notice.value = 'Selecione e copie o link.' } }
watch(query, scheduleSearch)
watch(mode, () => { sort.value = 'earliest' })
onBeforeUnmount(() => { clearTimeout(timer); availability.cancel() })
onMounted(async () => { busy.value = true; try { await load(); initialized.value = true; scheduleSearch(); const requested = appointments.value.find(a => Number(a.id) === Number(route.query.support)); if (requested) supportAppointment.value = requested } catch (e) { error.value = e.message } finally { busy.value = false } })
</script>
<template>
  <div class="page-heading"><div><p class="eyebrow">SEU PRÓXIMO PASSO</p><h1>Agendar e acompanhar</h1><p class="text-secondary mb-0">Os horários aparecem automaticamente. Escolha o que cabe na sua rotina.</p></div><button v-if="current?.kind === 'worker' && Number(current.user_id) === Number(auth.user.id) && current.status === 'active'" class="btn btn-outline-success" @click="familyOpen = true; invitation = ''; email = ''"><AppIcon name="people" :size="18" /> Convidar dependente</button></div>
  <p v-if="error && !chosen && !supportAppointment && !familyOpen" class="alert alert-danger" role="alert">{{ error }}</p><p v-if="notice" class="alert alert-success" role="status">{{ notice }}</p>
  <div v-if="!initialized && busy" class="loading-state" role="status"><span class="spinner-border spinner-border-sm" /> Consultando seu cadastro…</div>
  <template v-if="initialized">
    <section v-if="beneficiaries.length" class="card card-body search-panel">
      <div class="section-heading"><h2 class="h5">Encontrar atendimento</h2><span v-if="beneficiaries.length === 1" class="soft-tag">Para {{ current?.name }}</span></div>
      <div v-if="beneficiaries.length > 1" class="mb-4"><label for="beneficiary" class="form-label">Quem será atendido?</label><select id="beneficiary" v-model="patient" class="form-select" :disabled="busy"><option v-for="b in beneficiaries" :key="b.id" :value="b.id">{{ b.name }} · {{ b.kind === 'worker' ? 'Trabalhador' : 'Dependente' }}</option></select></div>
      <div class="search-controls"><div><span class="form-label d-block">Como prefere ser atendido?</span><div class="segmented-control"><button type="button" :class="{ active: mode === 'in_person' }" :aria-pressed="mode === 'in_person'" :disabled="busy" @click="mode = 'in_person'"><AppIcon name="pin" :size="18" /> Presencial</button><button type="button" :class="{ active: mode === 'telehealth' }" :aria-pressed="mode === 'telehealth'" :disabled="busy" @click="mode = 'telehealth'"><AppIcon name="video" :size="18" /> Teleconsulta</button></div></div><div><label for="professional-role" class="form-label">Profissional</label><select id="professional-role" v-model="professionalRole" class="form-select" :disabled="busy"><option value="doctor">Médico</option><option value="nurse">Enfermeiro</option><option value="all">Todos os profissionais</option></select></div></div>
      <div v-if="mode === 'in_person'" class="filter-toolbar mt-4 mb-0"><div class="filter-chips"><button :class="{ active: near === 'industry' }" :disabled="busy" @click="near = 'industry'">Perto da indústria</button><button :class="{ active: near === 'home' }" :disabled="busy || locating" @click="locate">{{ locating ? 'Obtendo localização…' : 'Perto de mim' }}</button></div><div class="filter-chips"><button :class="{ active: sort === 'earliest' }" :disabled="busy" @click="sort = 'earliest'">Primeiro horário</button><button :class="{ active: sort === 'distance' }" :disabled="busy" @click="sort = 'distance'">Menor distância</button></div></div>
      <label class="check-row mt-3"><input v-model="myTeam" type="checkbox" class="form-check-input" :disabled="busy">Mostrar somente minha equipe APS</label>
      <p v-if="current?.status !== 'active'" class="alert alert-info mt-3 mb-0">{{ current?.status === 'pending' ? 'Seu cadastro está aguardando validação. A gestão precisa confirmar contato e vínculo antes de liberar horários.' : 'Seu vínculo está suspenso. Procure a gestão para verificar sua elegibilidade.' }}</p>
    </section>
    <p v-else class="alert alert-info">Seu cadastro ainda não possui vínculo contratual. Procure a equipe responsável pelo convite.</p>
    <section v-if="current?.status === 'active'" class="mt-4" :aria-busy="searching || scheduled"><div class="section-heading"><h2 class="h5">{{ searching || scheduled ? 'Buscando horários…' : `${slots.length} horário(s) disponível(is)` }}</h2><small class="text-secondary">Próximos 90 dias · horário de Brasília</small></div><p v-if="searchError" class="alert alert-danger" role="alert">{{ searchError }} <button class="btn btn-link btn-sm" @click="scheduleSearch">Tentar novamente</button></p><p v-if="locationNeeded" class="alert alert-info">Use “Perto de mim” para autorizar sua localização nesta busca.</p><div v-if="searching || scheduled" class="row g-3" role="status"><div v-for="n in 3" :key="n" class="col-lg-4"><div class="skeleton-card" /></div><span class="visually-hidden">Buscando horários</span></div><div v-else-if="slots.length" class="row g-3"><div v-for="s in slots" :key="s.id" class="col-md-6 col-xl-4"><article class="card card-body slot-card h-100"><div class="d-flex justify-content-between align-items-center mb-3"><span class="avatar">{{ s.professional_name[0] }}</span><span v-if="s.distance_km !== null" class="soft-tag">{{ s.distance_km }} km aprox.</span></div><h3 class="h5 mb-1">{{ s.professional_name }}</h3><p class="small text-secondary mb-3">{{ s.role === 'doctor' ? 'Médico' : 'Enfermeiro' }} · {{ s.service_unit }}</p><strong class="slot-date">{{ formatDateTime(s.starts_at) }}</strong><p class="small text-secondary">{{ mode === 'telehealth' ? 'Atendimento por vídeo com link restrito' : s.address }}</p><button class="btn btn-success mt-auto" :disabled="busy" @click="choose(s)">Escolher horário <AppIcon name="arrow" :size="17" /></button></article></div></div><div v-else-if="!searchError && !locationNeeded" class="empty-state card"><AppIcon name="calendar" :size="34" /><h3 class="h6 mt-3">Nenhum horário para esta combinação.</h3><p>Experimente outra modalidade ou retire o filtro de equipe.</p><button v-if="myTeam" class="btn btn-outline-success" @click="myTeam = false">Ver todas as equipes disponíveis</button></div></section>
    <section class="card card-body mt-4"><div class="section-heading"><h2 class="h5">Meus atendimentos e retornos</h2><RouterLink to="/my-care">Meu cuidado →</RouterLink></div><AppointmentCard v-for="a in appointments" :key="a.id" :appointment="a" @refresh="load" @support="supportAppointment = $event; error = ''" /><p v-if="!appointments.length" class="empty-state">Seu próximo agendamento aparecerá aqui.</p></section>
  </template>
  <SidePanel :open="!!chosen" title="Confirmar atendimento" :busy="busy" @close="chosen = null"><template v-if="chosen"><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><div class="booking-summary"><span class="action-icon"><AppIcon :name="chosen.modality === 'telehealth' ? 'video' : 'calendar'" :size="28" /></span><h3 class="h4 mt-3">{{ formatDateTime(chosen.starts_at) }}</h3><p>{{ chosen.professional_name }}<br>{{ chosen.service_unit }}</p><p class="small text-secondary">Para {{ current?.name }} · {{ chosen.modality === 'telehealth' ? 'Teleconsulta' : 'Presencial' }}</p></div><form @submit.prevent="book"><label class="check-row my-4"><input v-model="authorized" class="form-check-input" type="checkbox" required :disabled="busy"><span>Autorizo o vínculo assistencial com a equipe escolhida para este acompanhamento.</span></label><button class="btn btn-success w-100" :disabled="busy || !authorized">{{ busy ? 'Confirmando…' : 'Confirmar agendamento' }}</button></form></template></SidePanel>
  <SidePanel :open="!!supportAppointment" title="Vamos organizar seu retorno" :subtitle="supportAppointment?.patient_name" :busy="busy" @close="supportAppointment = null"><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p class="text-secondary">Conte o que deseja compartilhar. A equipe será avisada para oferecer acolhimento.</p><FormPanel :key="supportAppointment?.id" :fields="supportFields" :busy="busy" submit-label="Pedir apoio à equipe" @submit="support" /></SidePanel>
  <SidePanel :open="familyOpen" title="Convidar dependente adulto" :busy="busy" @close="familyOpen = false"><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p class="text-secondary">O dependente terá conta própria e seu histórico ficará restrito. Para menores, procure a gestão para verificar a representação.</p><form v-if="!invitation" @submit.prevent="inviteDependent"><label for="dependent-email" class="form-label">E-mail do dependente</label><input id="dependent-email" v-model="email" class="form-control mb-3" type="email" required><button class="btn btn-success" :disabled="busy">Gerar convite</button></form><template v-else><input :value="invitation" class="form-control mb-3" readonly aria-label="Convite de cadastro" @focus="$event.target.select()"><button class="btn btn-success" @click="copyInvite">Copiar convite</button><p v-if="notice" class="small mt-3" role="status">{{ notice }}</p></template></SidePanel>
</template>
