<script setup>
import { computed, ref, watch } from 'vue'
import FormPanel from './FormPanel.vue'
import { apiGet, apiRequest } from '../services/api.js'
import { useLatestQuery } from '../composables/useLatestQuery.js'
import { shareDemo } from '../services/share-demo.js'
const props=defineProps({ companyId:[Number,String], companyName:String })
const query=useLatestQuery((options,id)=>apiGet('/management/gamification?company_id='+id,options))
const {data,loading,error:loadError}=query
const busy=ref(false),error=ref(''),notice=ref('')
const reactivationInitial=computed(()=>data.value?{reactivation_enabled:data.value.reactivation_enabled,reactivation_days:data.value.reactivation_days,reactivation_points:data.value.reactivation_points}:{})
const reactivationFields=[{name:'reactivation_enabled',label:'Reconhecer o médico que retoma o acompanhamento',type:'checkbox'},{name:'reactivation_days',label:'Dias sem atendimento ou acompanhamento registrado',type:'number',min:1,default:180},{name:'reactivation_points',label:'Pontos extras por retomada confirmada',type:'number',min:1,default:30}]
async function saveReactivation(values) {
  if(!data.value || busy.value || shareDemo) return
  const id=Number(props.companyId),body={enabled:data.value.enabled,version:data.value.version,...values}
  busy.value=true;error.value='';notice.value=''
  try { const updated=await apiRequest('/management/gamification?company_id='+id,'PUT',body);if(Number(props.companyId)===id) { data.value=updated;notice.value='Parâmetro de retomada salvo. Novos reconhecimentos seguem esta configuração; conquistas anteriores mantêm os pontos originais.' } }
  catch(e) { if(Number(props.companyId)===id) error.value=e.message } finally { busy.value=false }
}
watch(()=>props.companyId,id=>{ query.cancel(true);error.value='';notice.value='';if(id) query.run(id) },{immediate:true})
async function toggle() {
  if(!data.value || busy.value || shareDemo) return
  const companyId=Number(props.companyId), enabled=!data.value.enabled, version=data.value.version
  busy.value=true;error.value='';notice.value=''
  try {
    const updated=await apiRequest('/management/gamification?company_id='+companyId,'PUT',{enabled,version})
    if(Number(props.companyId)===companyId) { data.value=updated;notice.value=enabled?'Gamificação ativada para todos os trabalhadores desta empresa.':'Gamificação desativada. Histórico preservado e novos reconhecimentos pausados.' }
  } catch(e) { if(Number(props.companyId)===companyId) error.value=e.message } finally { busy.value=false }
}
</script>
<template>
  <section class="card card-body mb-4" aria-labelledby="company-gamification-title" :aria-busy="loading || busy" data-testid="company-gamification">
    <div class="section-heading flex-wrap gap-3"><div><p class="eyebrow">OPÇÃO DA EMPRESA</p><h2 id="company-gamification-title" class="h5">Gamificação do cuidado</h2><p class="small text-secondary mb-0">{{ companyName || 'Empresa selecionada' }} · Para todos os trabalhadores, incluindo novos cadastros.</p></div><div v-if="data" class="d-flex align-items-center gap-3"><span class="status-badge" :class="data.enabled?'active':'paused'">{{ data.enabled?'Ativada':'Desativada' }}</span><button class="btn btn-sm" :class="data.enabled?'btn-outline-secondary':'btn-success'" :disabled="busy || loading || shareDemo" :aria-pressed="data.enabled" @click="toggle">{{ busy?'Salvando…':data.enabled?'Desativar gamificação':'Ativar gamificação' }}</button></div></div>
    <p class="small text-secondary mt-3 mb-0">Quando ativada, cada trabalhador acompanha suas próprias conquistas. Quando desativada, a jornada fica oculta e nenhum novo ponto é reconhecido. O histórico é preservado. A gestão não recebe pontuação individual de saúde.</p>
    <details v-if="data" class="mt-3" data-testid="reactivation-settings"><summary>Bônus do médico por retomada</summary><div class="pt-3"><p class="small text-secondary">Um bônus por período sem registro, apenas após comparecimento ou acompanhamento profissional. Enfermagem também reinicia o período de acompanhamento. Não há bônus por simples agendamento nem mudança automática de risco clínico.</p><p v-if="!data.enabled" class="small text-secondary">Ative a gamificação da empresa para reconhecer novos pontos. Você pode preparar o parâmetro antes de ativar.</p><FormPanel :fields="reactivationFields" :initial="reactivationInitial" :busy="busy" submit-label="Salvar parâmetro de retomada" @submit="saveReactivation" /></div></details>
    <p v-if="loading" class="small mt-2 mb-0" role="status">Consultando a opção desta empresa…</p><p v-if="error || loadError" class="alert alert-danger mt-3 mb-0" role="alert">{{ error || loadError }} <button class="btn btn-sm btn-link" :disabled="busy || loading" @click="query.run(companyId)">Atualizar configuração</button></p><p v-if="notice" class="alert alert-success mt-3 mb-0" role="status">{{ notice }}</p><p v-if="shareDemo" class="small text-secondary mt-2 mb-0">Demonstração somente para consulta; esta opção não pode ser alterada no link público.</p>
  </section>
</template>
