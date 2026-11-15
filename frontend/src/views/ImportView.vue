<script setup>
import {onMounted,ref} from 'vue'
import {apiGet,apiRequest} from '../services/api.js'
import {canonical} from '../utils/import-aps.js'
import {formatarDataHora} from '../utils/format.js'
import {useAuthStore} from '../stores/auth.js'
import ApsImportReviews from '../components/ApsImportReviews.vue'
const auth=useAuthStore(),source=ref(null),report=ref(null),last=ref(null),generate=ref(false),busy=ref(false),error=ref(''),committed=ref(false),revision=ref(0)
async function choose(e){const file=e.target.files[0];source.value=null;report.value=null;committed.value=false;error.value='';if(!file)return;busy.value=true;try{
 if(file.size>5*1024*1024)throw Error('Limite de arquivo: 5 MB.');if(!/\.(csv|xlsx)$/i.test(file.name))throw Error('Use CSV ou XLSX.');
 const original_base64=await new Promise((resolve,reject)=>{const reader=new FileReader();reader.onload=()=>resolve(String(reader.result).split(',')[1]);reader.onerror=()=>reject(Error('Não foi possível ler o arquivo.'));reader.readAsDataURL(file)})
 const data=await apiRequest('/aps/import/inspect','POST',{filename:file.name,original_base64,generate:generate.value});source.value={filename:file.name,original_base64,headers:data.source.headers,mapping:data.source.mapping};report.value=data
 }catch(e){error.value=e.message}finally{busy.value=false}}
function invalidate(){report.value=null;committed.value=false}
async function submit(preview){busy.value=true;error.value='';try{
 report.value=await apiRequest(preview?'/aps/import/inspect':'/aps/import','POST',{filename:source.value.filename,original_base64:source.value.original_base64,mapping:source.value.mapping,generate:generate.value,preview})
 if(!preview){committed.value=true;last.value=await apiGet('/aps/import/last');revision.value++}
 }catch(e){error.value=e.message}finally{busy.value=false}}
onMounted(async()=>{try{last.value=await apiGet('/aps/import/last')}catch(e){error.value=e.message}})
</script>
<template>
 <p class="eyebrow">BASE CONSOLIDADA · CARGA VALIDADA</p><h1>Importar exportação (Klingo ou SOC/S+)</h1>
 <p>Selecione CSV ou XLSX. O servidor lê a primeira aba e mostra a prévia automaticamente. Limites: 1.000 linhas preenchidas e 5 MB. Obrigatórias: matrícula, nome, empresa e status.</p>
 <p v-if="auth.publicDemo" class="alert alert-info">Demonstração com banco fictício. Importe somente dados de teste; a carga desta visita fica separada.</p>
 <p data-demo="ultima-carga">Última carga: {{last?.created_at?formatarDataHora(last.created_at):'nenhuma nesta visita'}}</p>
 <p v-if="error" role="alert" class="alert alert-danger">{{error}}</p><label class="form-label" for="aps-file">Selecione a exportação</label><input id="aps-file" type="file" accept=".csv,.xlsx" :disabled="busy" class="form-control mb-3" @change="choose">
 <label v-if="auth.publicDemo" class="form-check mb-3"><input v-model="generate" :disabled="busy" @change="invalidate" type="checkbox" class="form-check-input">Simular cuidado fictício (opcional; não representa a exportação)</label>
 <p v-if="busy" role="status">Conferindo a carga…</p>
 <section v-if="source" class="card card-body" data-demo="previa-importacao"><h2 class="h5">Conferência antes de gravar</h2><p class="text-muted">Vazios preservam os valores anteriores. Observações sem identificação ficam separadas. Nenhum cuidado é inferido da falta de informação.</p>
  <p v-if="report?.source">{{report.source.headers.length}} colunas · {{report.source.rows}} linhas preenchidas · leitura no servidor<span v-if="report.source.formula_count"> · {{report.source.formula_count}} fórmulas lidas pelo valor salvo, sem execução</span></p>
  <details class="mb-3"><summary>Conferir ou ajustar o mapeamento das colunas</summary><div class="row g-2 mt-1"><label v-for="(header,i) in source.headers" :key="i" class="col-md-4 text-break">{{header}}<select v-model="source.mapping[i]" :disabled="busy" class="form-select" @change="invalidate"><option value="">Preservar sem interpretar</option><option v-for="(aliases,key) in canonical" :key="key" :value="key">{{aliases[0]}}</option></select></label></div></details>
  <button v-if="!report" class="btn btn-outline-success" :disabled="busy" @click="submit(true)">Validar alterações</button>
  <template v-if="report"><p>{{report.valid}} válidas · {{report.errors.length}} erros · {{report.review?.length||0}} observações sem vínculo · {{report.warnings.length}} avisos</p>
   <p v-if="committed" role="status" class="alert alert-success">{{report.reused?'Carga já processada; nenhum registro duplicado.':report.imported+' registros importados; '+(report.unchanged||0)+' sem alteração. Os apontamentos estão na revisão abaixo.'}}</p>
   <button v-else-if="report.valid" class="btn btn-success" :disabled="busy" @click="submit(false)">Confirmar importação de {{report.valid}} linhas válidas</button>
   <p v-if="report.valid && !committed" class="small mt-2 mb-0">As linhas inválidas e observações sem vínculo serão preservadas para revisão. CPFs inválidos geram aviso e não são usados para vincular pessoas.</p>
   <details v-if="report.errors.length" class="mt-3" open><summary>Erros · {{report.errors.length}}</summary><p v-for="(item,i) in report.errors" :key="i">Linha {{item.line}}: {{item.message}}</p></details>
   <details v-if="report.review?.length" class="mt-3" open><summary>Observações sem vínculo · {{report.review.length}}</summary><p v-for="(item,i) in report.review" :key="i">Linha {{item.line}}: {{item.message}}</p></details>
   <details v-if="report.warnings.length" class="mt-3"><summary>Avisos · {{report.warnings.length}}</summary><p v-for="(item,i) in report.warnings" :key="i">Linha {{item.line}}: {{item.message}}</p></details>
   <div v-if="report.preview.length" class="table-responsive mt-3"><table class="table"><caption>Amostra de até 10 linhas válidas; CPF e telefone protegidos.</caption><thead><tr><th>Matrícula</th><th>Nome</th><th>CPF</th><th>Telefone</th><th>Status</th></tr></thead><tbody><tr v-for="p in report.preview" :key="p.registration"><td>{{p.registration}}</td><td>{{p.name}}</td><td>{{p.cpf}}</td><td>{{p.phone}}</td><td>{{p.status}}</td></tr></tbody></table></div>
  </template>
 </section>
 <ApsImportReviews :revision="revision" />
</template>
