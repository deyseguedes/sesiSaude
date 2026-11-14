<script setup>
import { ref } from 'vue'
import { apiGet, apiRequest } from '../services/api.js'
import { label } from '../utils.js'
import { formatDateTime } from '../utils/workflow.js'
import AppIcon from './AppIcon.vue'
const props = defineProps({ appointment: { type: Object, required: true } })
const emit = defineEmits(['refresh', 'support'])
const busy = ref(false), error = ref(''), fallbackLink = ref('')
async function confirm() {
  busy.value = true; error.value = ''
  try { await apiRequest(`/me/appointments/${props.appointment.id}/confirm`, 'POST'); emit('refresh') }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
async function enter() {
  const popup = window.open('about:blank', '_blank')
  if (popup) { popup.opener = null; popup.document.title = 'Acesso à teleconsulta'; popup.document.body.textContent = 'Verificando seu acesso à teleconsulta…' }
  busy.value = true; error.value = ''
  try { const { meeting_url } = await apiGet(`/appointments/${props.appointment.id}/access`); if (popup) popup.location.replace(meeting_url); else fallbackLink.value = meeting_url }
  catch (e) { popup?.close(); error.value = e.message } finally { busy.value = false }
}
</script>
<template><article class="appointment-card"><div class="appointment-type"><AppIcon :name="appointment.modality === 'telehealth' ? 'video' : 'calendar'" /></div><div class="appointment-info"><strong>{{ formatDateTime(appointment.scheduled_at) }}</strong><span>{{ appointment.professional_name }} · {{ appointment.patient_name }}</span><small>{{ appointment.modality === 'telehealth' ? 'Teleconsulta' : appointment.service_unit || 'Presencial' }}</small></div><span class="status-badge" :class="appointment.status">{{ label(appointment.status) }}</span><div class="appointment-actions"><button v-if="appointment.status === 'expected'" class="btn btn-success btn-sm" :disabled="busy" @click="confirm">Confirmar retorno</button><button v-if="appointment.modality === 'telehealth' && ['expected', 'confirmed'].includes(appointment.status)" class="btn btn-success btn-sm" :disabled="busy" @click="enter">Entrar na teleconsulta</button><button v-if="['expected', 'confirmed'].includes(appointment.status)" class="btn btn-outline-secondary btn-sm" :disabled="busy" @click="emit('support', appointment)">Preciso de apoio</button></div><p v-if="error" role="alert" class="text-danger small w-100 mb-0">{{ error }}</p><a v-if="fallbackLink" :href="fallbackLink" target="_blank" rel="noopener noreferrer" class="btn btn-success btn-sm">Abrir sala de atendimento</a></article></template>
