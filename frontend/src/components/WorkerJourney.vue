<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { apiGet, apiRequest } from '../services/api.js'
import { shareDemo } from '../services/share-demo.js'
import AppIcon from './AppIcon.vue'
const journey=ref(null),error=ref(''),loading=ref(false)
const emit=defineEmits(['availability'])
async function load() {
  if(loading.value) return
  loading.value=true;error.value=''
  try { journey.value=shareDemo?await apiGet('/me/journey'):await apiRequest('/me/journey/sync','POST');emit('availability',journey.value.enabled===true) }
  catch(e) { error.value=e.message } finally { loading.value=false }
}
onMounted(() => { load(); window.addEventListener('focus',load) })
onBeforeUnmount(() => window.removeEventListener('focus',load))
const icons={ scheduled:'calendar',attended:'check',followup:'heart' }
</script>
<template>
  <section v-if="journey?.enabled || error" class="worker-journey card card-body mb-4" aria-labelledby="journey-title" :aria-busy="loading" data-testid="worker-journey">
    <div class="section-heading flex-wrap gap-3"><div><p class="eyebrow mb-2">CADA PASSO CONTA</p><h2 id="journey-title" class="h4 mb-1">Minha jornada de cuidado</h2><p class="text-secondary small mb-0">Conquistas pessoais por organizar e continuar seu acompanhamento.</p></div><div v-if="journey?.available" class="journey-score"><AppIcon name="heart" :size="24" /><strong>{{ journey.points }}</strong><span>pontos da jornada</span></div></div>
    <p v-if="error" class="alert alert-danger mt-3 mb-0" role="alert">Não foi possível atualizar suas conquistas. <button class="btn btn-sm btn-link" :disabled="loading" @click="load">Tentar novamente</button></p>
    <p v-else-if="loading && !journey" class="small text-secondary mt-3 mb-0" role="status">Consultando sua jornada…</p>
    <template v-if="journey?.available"><div class="journey-level mt-3"><span class="soft-tag">{{ journey.level.name }}</span><span class="small text-secondary">{{ journey.completed }} de {{ journey.total }} conquistas</span></div><div class="journey-track" role="progressbar" aria-label="Etapas reconhecidas da jornada" :aria-valuenow="journey.completed" aria-valuemin="0" :aria-valuemax="journey.total"><span :style="{ width: (100 * journey.completed / journey.total) + '%' }" /></div>
      <div class="journey-missions"><article v-for="mission in journey.missions" :key="mission.code" class="journey-mission" :class="{ earned: mission.completed }" :data-mission="mission.code"><span class="journey-medal"><AppIcon :name="icons[mission.code]" :size="25" /></span><div><strong>{{ mission.title }}</strong><span class="journey-points">+{{ mission.points }} pontos <span v-if="mission.completed">· Conquista reconhecida</span><span v-else>· {{ mission.code === 'scheduled' ? 'Próximo passo' : 'Aguardando registro da equipe' }}</span></span><span class="journey-mobile-state">{{ mission.completed ? 'Conquistada' : 'Pendente' }}</span><p class="small text-secondary mb-0">{{ mission.description }}</p></div></article></div>
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3"><p class="small mb-0">{{ journey.completed === journey.total ? 'Sua jornada está completa. Continue o cuidado no ritmo pactuado com sua equipe.' : journey.next_level ? `Próximo reconhecimento: ${journey.next_level.name}.` : 'Seu acompanhamento faz a diferença.' }}</p><RouterLink v-if="!journey.missions[0].completed" to="/find-care" class="btn btn-success btn-sm">Encontrar atendimento →</RouterLink><RouterLink v-else to="/find-care" class="btn btn-outline-success btn-sm">Ver meus retornos →</RouterLink></div>
      <details class="small text-secondary mt-3"><summary>Como as conquistas funcionam</summary><p class="mt-2 mb-0">Cada conquista vale uma única vez nesta jornada. Os pontos são reconhecimentos simbólicos, sem valor financeiro e sem comparação com colegas. Não é preciso resgatar. Comparecimento e acompanhamento dependem dos registros da equipe; uma resposta sua não confirma uma consulta realizada. Reagendar ou pedir apoio não gera punição. Os pontos não medem sua saúde nem mudam seu acesso ao atendimento.</p><ul class="d-md-none mt-2 mb-0"><li v-for="mission in journey.missions" :key="mission.code"><strong>{{ mission.title }}:</strong> {{ mission.description }}</li></ul></details>
    </template><p v-else-if="journey && !journey.available" class="small text-secondary mt-3 mb-0">Sua jornada aparecerá quando seu cadastro de trabalhador estiver vinculado.</p>
  </section>
</template>
