<script setup>
import {ref,watch,onMounted,computed} from 'vue'
import {apiGet,apiRequest} from '../services/api.js'
import {formatarDataHora} from '../utils/format.js'
const props=defineProps({revision:{type:Number,default:0}})
const labels={CPF_INVALID:'CPF para conferência',RETURN_MISSING:'Retorno não informado',AGE_CACHE_DIFFERENCE:'Idade divergente',ORPHAN_OBSERVATION:'Observação sem identificação',INVALID_ROW:'Linha inválida'}
const status=ref('pending'),code=ref(''),page=ref(1),result=ref(null),selected=ref(null),note=ref(''),decision=ref('reviewed'),busy=ref(false),error=ref(''),notice=ref('')
const pending=computed(()=>result.value?.summary.filter(item=>item.status==='pending').reduce((sum,item)=>sum+Number(item.total),0)||0)
let sequence=0
async function load(){const request=++sequence;busy.value=true;error.value='';result.value=null;try{const data=await apiGet(`/aps/import/reviews?status=${status.value}&code=${code.value}&page=${page.value}`);if(request===sequence)result.value=data}catch(e){if(request===sequence)error.value=e.message}finally{if(request===sequence)busy.value=false}}
function filter(){page.value=1;selected.value=null;load()}
async function open(id){busy.value=true;error.value='';notice.value='';selected.value=null;try{selected.value=await apiGet(`/aps/import/reviews/${id}`);note.value=selected.value.item.note||'';decision.value='reviewed'}catch(e){error.value=e.message}finally{busy.value=false}}
async function save(){if(!note.value.trim()){error.value='Registre o que foi conferido antes de salvar.';return}busy.value=true;error.value='';try{await apiRequest(`/aps/import/reviews/${selected.value.item.id}`,'PUT',{status:decision.value,note:note.value,version:selected.value.item.version});selected.value=null;notice.value='Conferência registrada. A origem e os dados do cuidado foram preservados.';await load()}catch(e){error.value=e.message}finally{busy.value=false}}
watch(()=>props.revision,()=>{selected.value=null;page.value=1;load()})
onMounted(load)
</script>
<template>
 <section class="card card-body mt-4" aria-labelledby="source-reviews-title" data-demo="revisoes-importacao">
  <div class="d-flex flex-wrap justify-content-between gap-2"><h2 id="source-reviews-title" class="h5">Revisão das cargas confirmadas</h2><span>{{pending}} apontamentos pendentes</span></div>
  <p class="text-muted">Conferência técnica por empresa e perfil. Marcar como revisado não corrige a origem, não vincula observações a pessoas e não comprova atendimento.</p>
  <p v-if="error" role="alert" class="alert alert-danger">{{error}}</p><p v-if="notice" role="status" class="alert alert-success">{{notice}}</p>
  <div class="row g-2 mb-3"><label class="col-sm-6">Situação<select v-model="status" :disabled="busy" class="form-select" @change="filter"><option value="pending">Pendentes</option><option value="reviewed">Revisados</option><option value="all">Todos</option></select></label><label class="col-sm-6">Motivo<select v-model="code" :disabled="busy" class="form-select" @change="filter"><option value="">Todos os motivos</option><option v-for="(label,key) in labels" :key="key" :value="key">{{label}}</option></select></label></div>
  <p v-if="!result && busy" role="status">Carregando revisões…</p>
  <template v-if="result"><p v-if="!result.total">Nenhum apontamento nesta seleção.</p><div v-else class="table-responsive"><table class="table align-middle"><thead><tr><th>Carga</th><th>Linha</th><th>Motivo</th><th>Situação</th><th>Ação</th></tr></thead><tbody><tr v-for="item in result.items" :key="item.id"><td>#{{item.archive_id}}</td><td>{{item.source_line}}</td><td>{{labels[item.code]||item.message}}</td><td>{{item.status==='pending'?'Pendente':'Revisado'}}</td><td><button class="btn btn-sm btn-outline-success" :disabled="busy" @click="open(item.id)">Conferir<span class="visually-hidden"> linha {{item.source_line}} da carga {{item.archive_id}}</span></button></td></tr></tbody></table></div>
   <div v-if="result.total>result.page_size" class="d-flex justify-content-between align-items-center"><button class="btn btn-outline-secondary" :disabled="busy||page===1" @click="page--;load()">Anterior</button><span>Página {{page}} de {{Math.ceil(result.total/result.page_size)}}</span><button class="btn btn-outline-secondary" :disabled="busy||page*result.page_size>=result.total" @click="page++;load()">Próxima</button></div>
  </template>
  <section v-if="selected" class="border rounded p-3 mt-3" aria-labelledby="source-review-detail"><div class="d-flex justify-content-between gap-2"><h3 id="source-review-detail" class="h6">Linha {{selected.item.source_line}} · {{labels[selected.item.code]||'Conferência'}}</h3><button class="btn btn-sm btn-outline-secondary" :disabled="busy" @click="selected=null">Fechar</button></div><p>{{selected.item.message}}</p><p v-if="selected.sheet_name" class="small text-muted">Aba: {{selected.sheet_name}}</p>
   <dl v-if="selected.cells.length" class="row mb-2"><template v-for="(cell,index) in selected.cells" :key="index"><dt class="col-md-6 text-break">{{cell.header}}</dt><dd class="col-md-6 text-break">{{cell.value}}</dd></template></dl><p v-else class="text-muted">Campo sem valor recebido. Isso não comprova ausência de cuidado.</p>
   <p v-if="selected.item.reviewed_at" class="small">Última conferência: {{formatarDataHora(selected.item.reviewed_at)}}</p>
   <form @submit.prevent="save"><label class="form-label w-100">Anotação da conferência<textarea v-model="note" class="form-control" maxlength="1000" rows="3" required :disabled="busy" placeholder="Descreva a verificação e o encaminhamento necessário, sem inventar dados clínicos."></textarea></label><label class="form-label w-100">Situação após a conferência<select v-model="decision" class="form-select" :disabled="busy"><option value="reviewed">Revisado</option><option value="pending">Manter pendente</option></select></label><button type="submit" class="btn btn-success" :disabled="busy||!note.trim()">Registrar conferência</button></form>
  </section>
 </section>
</template>
