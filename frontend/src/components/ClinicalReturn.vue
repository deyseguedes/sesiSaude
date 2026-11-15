<script setup>
import { computed, ref, watch } from 'vue'
import { apiGet, apiRequest } from '../services/api.js'
import { useAuthStore } from '../stores/auth.js'
import { shareDemo } from '../services/share-demo.js'
import { today } from '../utils.js'
import { formatDate } from '../utils/workflow.js'
import { suggestedReturn } from '../utils/clinical-return.js'
const props=defineProps({patientId:Number,teams:{type:Array,default:()=>[]}})
const emit=defineEmits(['saved'])
const auth=useAuthStore(),record=ref(null),risk=ref(''),due=ref(''),assessed=ref(today()),team=ref(''),reason=ref(''),error=ref(''),notice=ref(''),busy=ref(false),loading=ref(false)
let generation=0,manualDate=false
const doctor=computed(()=>auth.user?.role==='doctor')
const lowering=computed(()=>record.value?.available && Number(risk.value)<record.value.risk)
const status={completed:'Retorno realizado',overdue:'Retorno atrasado',today:'Retorno devido hoje',upcoming:'Retorno previsto'}
async function load() {
 const current=++generation;record.value=null;error.value='';notice.value='';risk.value='';due.value='';assessed.value=today();reason.value='';manualDate=false;loading.value=true
 try {
  const value=await apiGet('/clinical/patients/'+props.patientId+'/return')
  if(current!==generation)return
  record.value=value;risk.value=value.available?value.risk:'';due.value=value.due_date||'';assessed.value=today();team.value=value.team_id||(props.teams.length===1?props.teams[0].id:'');manualDate=!!value.available
 }catch(e){if(current===generation)error.value=e.message}finally{if(current===generation)loading.value=false}
}
watch(()=>props.patientId,load,{immediate:true})
function suggest(){if(!manualDate)due.value=suggestedReturn(Number(risk.value),assessed.value)}
async function save(){
 if(busy.value||loading.value||shareDemo)return
 busy.value=true;error.value='';notice.value='';const current=generation
 try {
  const value=await apiRequest('/clinical/patients/'+props.patientId+'/return','PUT',{risk:Number(risk.value),due_date:due.value,assessment_date:assessed.value,team_id:Number(team.value),reason:reason.value,version:record.value?.version??0})
  if(current===generation){record.value=value;manualDate=true;reason.value='';notice.value='Risco e retorno salvos. A fila da enfermagem acompanha o prazo automaticamente.';emit('saved')}
 }catch(e){if(current===generation)error.value=e.message}finally{busy.value=false}
}
</script>
<template>
<section class="card card-body mb-4" data-testid="clinical-return">
 <div class="section-heading"><div><p class="eyebrow">CONTINUIDADE DO CUIDADO</p><h2 class="h5">Risco confirmado e próximo retorno</h2></div><span v-if="record?.available" class="status-badge">{{ status[record.status] }}</span></div>
 <p v-if="loading" class="small" role="status">Consultando o retorno…</p>
 <p v-if="error" class="alert alert-danger" role="alert">{{ error }} <button class="btn btn-link btn-sm" :disabled="busy" @click="load">Atualizar registro</button></p>
 <p v-if="notice" class="alert alert-success" role="status">{{ notice }}</p>
 <form v-if="doctor && record" class="row g-3" @submit.prevent="save">
  <div class="col-md-4"><label class="form-label" for="return-risk">Risco confirmado pelo médico</label><select id="return-risk" v-model="risk" class="form-select" required :disabled="busy||shareDemo" @change="suggest"><option value="" disabled>Selecione</option><option v-for="n in 4" :key="n" :value="n">Risco {{ n }}</option></select></div>
  <div class="col-md-4"><label class="form-label" for="return-date">Data do próximo retorno</label><input id="return-date" v-model="due" type="date" class="form-control" required :disabled="busy||shareDemo" @input="manualDate=true"></div>
  <div v-if="teams.length>1" class="col-md-4"><label class="form-label" for="return-team">Equipe de referência</label><select id="return-team" v-model="team" class="form-select" required :disabled="busy||shareDemo"><option v-for="t in teams" :key="t.id" :value="t.id">{{ t.name }}</option></select></div>
  <div v-if="lowering" class="col-12"><label class="form-label" for="return-reason">Justificativa do rebaixamento</label><textarea id="return-reason" v-model="reason" class="form-control" maxlength="1000" required :disabled="busy||shareDemo" /></div>
  <div class="col-12"><details><summary>Data da avaliação e observação</summary><label for="return-assessment" class="form-label mt-2">Data da avaliação médica</label><input id="return-assessment" v-model="assessed" type="date" class="form-control" :max="today()" :disabled="busy||shareDemo" required @change="suggest"><template v-if="!lowering"><label for="return-note" class="form-label mt-2">Observação opcional</label><textarea id="return-note" v-model="reason" class="form-control" maxlength="1000" :disabled="busy||shareDemo" /></template></details></div>
  <div class="col-12 d-flex flex-wrap gap-3 align-items-center"><button class="btn btn-success" :disabled="busy||loading||shareDemo||!team">{{ busy?'Salvando…':'Salvar risco e retorno' }}</button><small class="text-secondary">A data é confirmada pelo médico. Não cria consulta automaticamente.</small></div>
  <p class="small text-secondary mb-0">Referência da proposta: riscos 1 e 2, seis meses; risco 3, três meses; risco 4, data definida pelo médico. A sugestão inicial deve ser conferida conforme o protocolo SESI.</p>
 </form>
 <p v-else-if="record?.available" class="mb-0"><strong>Risco {{ record.risk }}</strong> · retorno em {{ formatDate(record.due_date) }} · {{ record.doctor_name }}</p>
 <p v-else-if="record" class="text-secondary mb-0">Ainda não há risco e retorno confirmados pelo médico.</p>
 <p v-if="record?.available" class="small text-secondary mt-3 mb-0">Avaliação: {{ formatDate(record.assessment_date) }} · {{ record.team_name }} · confirmado por {{ record.doctor_name }}. {{ record.attendance?'Comparecimento médico posterior registrado em '+formatDate(record.attendance.scheduled_at.slice(0,10))+'.':'Agendar ou confirmar horário não comprova comparecimento.' }}</p>
 <details v-if="record?.history?.length" class="small mt-3"><summary>Histórico de confirmações</summary><p v-for="e in record.history" :key="e.id" class="border-top pt-2 mb-2">{{ e.author_name }} · {{ e.created_at }} · versão {{ e.version }}<br>Risco {{ e.payload.after.risk }} · retorno {{ formatDate(e.payload.after.due_date) }}<br>{{ e.payload.reason }}</p></details>
 <p v-if="shareDemo" class="small text-secondary mt-2 mb-0">Demonstração somente para consulta.</p>
</section>
</template>
