<script setup>
import { formatDateTime } from "../utils/workflow.js"
import { onMounted, ref } from 'vue'
import { apiGet, apiRequest } from '../services/api.js'
import { label } from '../utils.js'
import { shareDemo } from '../services/share-demo.js'
const appointments = ref([]), urls = ref({}), access = ref({}), error = ref(''), notice = ref(''), busy = ref(false), opening = ref(null)
async function save(a) { busy.value = true; error.value = ''; try { await apiRequest(`/appointments/${a.id}/access`, 'PUT', { meeting_url: urls.value[a.id] || '' }); urls.value[a.id] = ''; notice.value = 'Link guardado com acesso restrito. Não será exibido à gestão empresarial.' } catch (e) { error.value = e.message } finally { busy.value = false } }
async function enter(a) {
  if (opening.value !== null || shareDemo) return
  let popup
  try { popup = window.open('about:blank', '_blank'); if (popup) { popup.opener = null; popup.document.title = 'Acesso à teleconsulta'; popup.document.body.textContent = 'Verificando seu acesso à sala…' } } catch {}
  opening.value = a.id; error.value = ''; access.value[a.id] = ''
  try {
    const { meeting_url } = await apiGet(`/appointments/${a.id}/access`)
    if (popup && !popup.closed) popup.location.replace(meeting_url)
    else access.value[a.id] = meeting_url
  } catch (e) { popup?.close(); error.value = e.message } finally { opening.value = null }
}
onMounted(async () => { busy.value = true; try { appointments.value = await apiGet('/professional/appointments') } catch (e) { error.value = e.message } finally { busy.value = false } })
</script>
<template><p class="eyebrow">MINHA AGENDA ASSISTENCIAL</p><h1>Atendimentos presenciais e teleconsultas</h1><p class="text-secondary">Seus pacientes vinculados, dos últimos sete dias aos próximos noventa dias.</p><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p v-if="notice" class="alert alert-success" role="status">{{ notice }}</p><p v-if="busy">Processando…</p><p v-if="!busy && !appointments.length">Nenhum atendimento neste período.</p><div class="row g-4"><div v-for="a in appointments" :key="a.id" class="col-lg-6"><article class="card card-body h-100"><h2 class="h5"><RouterLink :to="`/patients/${a.patient_id}`">{{ a.patient_name }}</RouterLink></h2><p>{{ formatDateTime(a.scheduled_at) }} · {{ a.modality === 'telehealth' ? 'Teleconsulta' : 'Presencial' }} · {{ label(a.status) }}<br><small>{{ a.service_unit }}</small></p><template v-if="a.modality === 'telehealth' && ['confirmed', 'expected'].includes(a.status)"><p v-if="shareDemo" class="small text-secondary">Demonstração somente para consulta. Configuração e acesso a salas estão desabilitados.</p><button class="btn btn-success align-self-start mb-3" :disabled="busy || opening !== null || shareDemo" @click="enter(a)">{{ opening === a.id ? 'Verificando acesso…' : 'Entrar na sala' }}</button><details v-if="!shareDemo"><summary>Configurar link restrito</summary><form class="mt-3" @submit.prevent="save(a)"><label :for="`room-${a.id}`" class="form-label">Link HTTPS exclusivo desta teleconsulta</label><input :id="`room-${a.id}`" v-model="urls[a.id]" class="form-control mb-2" type="url" required maxlength="2000" placeholder="https://seu-provedor/sala"><button class="btn btn-success" :disabled="busy">Guardar link restrito</button></form></details><p class="small text-secondary mt-3">Use o provedor autorizado pela instituição. A plataforma organiza o acesso; a chamada acontece no provedor informado.</p><a v-if="access[a.id]" :href="access[a.id]" target="_blank" rel="noopener noreferrer" class="btn btn-success mt-2">Entrar na sala</a></template></article></div></div></template>
