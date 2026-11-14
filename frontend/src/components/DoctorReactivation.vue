<script setup>
import { onMounted, ref } from 'vue'
import { apiGet, apiRequest } from '../services/api.js'
import { shareDemo } from '../services/share-demo.js'
const data=ref(null),error=ref(''),loading=ref(false)
async function load() { loading.value=true;error.value='';try { data.value=shareDemo?await apiGet('/professional/reactivation'):await apiRequest('/professional/reactivation/sync','POST') } catch(e) { error.value=e.message } finally { loading.value=false } }
onMounted(load)
</script>
<template>
  <section v-if="data?.enabled || error" class="card card-body mb-4" data-testid="doctor-reactivation" :aria-busy="loading"><div class="section-heading flex-wrap gap-3"><div><p class="eyebrow">RECONHECIMENTO DA CONTINUIDADE</p><h2 class="h5">Retomadas de acompanhamento</h2><p v-if="data?.enabled" class="small text-secondary mb-0">Bônus de {{ data.bonus_points }} pontos por retomada documentada após {{ data.threshold_days }} dias sem atendimento ou acompanhamento registrado.</p></div><div v-if="data?.enabled" class="journey-score"><strong>{{ data.points }}</strong><span>pontos em retomadas</span></div></div>
    <p v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">{{ error }} <button class="btn btn-sm btn-link" :disabled="loading" @click="load">Atualizar</button></p>
    <template v-if="data?.enabled"><p class="small mt-3 mb-2">{{ data.reactivations }} retomada(s) reconhecida(s). Pontos simbólicos, sem ranking ou valor financeiro.</p><div v-for="p in data.opportunities" :key="p.patient_id" class="list-entry"><div><strong>{{ p.patient_name }}</strong><small>{{ p.days_without_record }} dias sem registro na plataforma · retomada elegível a +{{ data.bonus_points }} pontos</small></div><RouterLink :to="`/patients/${p.patient_id}?section=records`" class="btn btn-sm btn-outline-success ms-auto">Abrir acompanhamento</RouterLink></div><details v-if="data.items.length" class="small mt-3"><summary>Retomadas reconhecidas</summary><p v-for="p in data.items" :key="p.patient_id + ':' + p.earned_at" class="border-top py-2 mb-0">{{ p.patient_name }} · intervalo de {{ p.gap_days }} dias · +{{ p.points }} pontos</p></details><p class="small text-secondary mt-3 mb-0">Conta o médico do atendimento realizado ou autor do acompanhamento. Agendar, confirmar horário e repetir registros não liberam bônus. A ausência de registros não prova ausência de atendimento fora da plataforma e não é uma classificação de risco clínico.</p></template>
  </section>
</template>
