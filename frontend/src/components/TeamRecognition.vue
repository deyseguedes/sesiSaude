<script setup>
import { computed, ref, watch } from 'vue'
import { apiGet, apiRequest } from '../services/api.js'
import { useAuthStore } from '../stores/auth.js'
import { shareDemo } from '../services/share-demo.js'
import FormPanel from './FormPanel.vue'
const props=defineProps({companyId:[Number,String],management:Boolean})
const auth=useAuthStore()
const data=ref(null),error=ref(''),busy=ref(false),result=ref(null)
let generation=0
const initial=computed(()=>({team_id:data.value?.teams[0]?.id||'',period:'quarter',excluded_count:0}))
const fields=computed(()=>[
 {name:'team_id',label:'Equipe de referência',options:(data.value?.teams||[]).map(t=>({value:t.id,label:t.name}))},
 {name:'period',label:'Período proposto',options:[{value:'quarter',label:'Trimestre'},{value:'semester',label:'Semestre'},{value:'regional',label:'Regional'}]},
 {name:'due_count',label:'Retornos devidos na carteira congelada',type:'number',min:0},
 {name:'excluded_count',label:'Exclusões com fonte objetiva',type:'number',min:0},
 {name:'on_time_count',label:'Retornos realizados no prazo',type:'number',min:0},
 {name:'baseline_percent',label:'Linha de base (%)',type:'number',min:0},
 {name:'satisfaction_percent',label:'Satisfação (%)',type:'number',min:0},
 ...[['frozen_verified','Carteira congelada e exclusões verificadas'],['baseline_verified','Linha de base verificada'],['no_upheld_pressure','Verificação concluída: nenhuma queixa de pressão procedente'],['risk_audit_passed','Auditoria de risco e exclusões liberada'],['consent_compliant','Contatos respeitam autorizações vigentes']].map(([name,label])=>({name,label,type:'checkbox'}))
])
async function load() {
 const current=++generation;data.value=null;result.value=null;error.value=''
 try { const value=await apiGet('/recognition/teams'+(props.companyId?'?company_id='+props.companyId:''));if(auth.publicDemo && (!props.companyId||Number(props.companyId)===Number(auth.user.company_id)))value.example=(await apiGet('/demo/management')).recognition;if(current===generation)data.value=value }
 catch(e){if(current===generation)error.value=e.message}
}
watch(()=>props.companyId,load,{immediate:true})
async function simulate(values) {
 if(busy.value||shareDemo)return
 const current=generation;busy.value=true;error.value='';result.value=null
 try {const value=await apiRequest('/recognition/teams/simulate'+(props.companyId?'?company_id='+props.companyId:''),'POST',values);if(current===generation)result.value=value}
 catch(e){if(current===generation)error.value=e.message}finally{busy.value=false}
}
const number=value=>value===null?'Não apurável':new Intl.NumberFormat('pt-BR',{minimumFractionDigits:1,maximumFractionDigits:1}).format(value)
</script>
<template>
<section class="card card-body mb-4" data-testid="team-recognition">
 <p class="eyebrow">RECONHECIMENTO COLETIVO · PROPOSTA EM DISCUSSÃO</p><h2 class="h5">Prêmio da equipe de saúde</h2>
 <p class="small text-secondary">Acolhimento, enfermagem e médico compartilham o mesmo percentual proposto. O reconhecimento coletivo substitui os pontos individuais do médico. A simulação não autoriza pagamento.</p>
 <p v-if="error" class="alert alert-danger" role="alert">{{ error }} <button class="btn btn-link btn-sm" @click="load">Atualizar</button></p>
 <div v-if="data" class="table-responsive"><table class="table"><thead><tr><th>Equipe</th><th>Retorno no prazo</th><th>Linha de base</th><th>Faixa do prêmio</th><th>Apuração</th></tr></thead><tbody><tr v-for="t in data.teams" :key="t.id"><td>{{ t.name }}</td><template v-if="data.example && t.name === data.example.name"><td>{{ number(data.example.result.rp_percent) }}%</td><td>{{ number(data.example.result.baseline_percent) }}%</td><td>{{ data.example.result.proposed_percent }}% · ganho {{number(data.example.result.gain_pp)}} pontos</td><td>Travas aprovadas no exemplo. {{data.example.source}}</td></template><template v-else><td>Não consolidado</td><td>Não consolidada</td><td>Não apurável</td><td>{{ t.reason }}</td></template></tr><tr v-if="!data.teams.length"><td colspan="5">Nenhuma equipe disponível neste acesso.</td></tr></tbody></table></div>
 <details v-if="management && data?.teams.length"><summary>Simular cenário da equipe</summary><p class="small mt-3">Informe apenas totais agregados. Esta simulação manual não substitui a carteira congelada nem a validação institucional. Mínimo proposto: 300 retornos após exclusões; abaixo disso, semestre e depois regional.</p><FormPanel :fields="fields" :initial="initial" :busy="busy" submit-label="Simular prêmio coletivo" @submit="simulate" />
 <div v-if="result" class="alert alert-info mt-3" data-testid="team-simulation-result"><strong>Cenário simulado · pagamento não autorizado</strong><p class="mb-1">RP: {{ number(result.rp_percent) }}% · linha de base: {{ number(result.baseline_percent??null) }}% · ganho: {{ number(result.gain_pp) }} pontos percentuais.</p><p v-if="result.proposed_percent!==null">Faixa teórica: {{ result.proposed_percent }}% do salário-base do período, igual para os integrantes e proporcional aos dias na equipe.</p><p v-for="reason in result.blockers" :key="reason" class="mb-1">{{ reason }}</p><p v-if="!result.blockers.length" class="mb-0">As condições informadas passam na simulação. A apuração oficial depende de evidências e regulamento aprovado.</p></div></details>
 <details class="small mt-3"><summary>Como calculamos a proposta</summary><p class="mt-2">RP = retornos no prazo ÷ retornos devidos após exclusões objetivas. Contato perdido e recusa por telefone permanecem no denominador. Ganho sobre a linha de base: 4, 6, 8, 10 e 12 pontos percentuais correspondem a 1%, 2%, 3%, 4% e 5%. Patamar de excelência proposto: RP de 80%.</p><p>Exige satisfação de pelo menos 90%, verificação de pressão, auditoria de risco/exclusões e respeito às autorizações. Tolerância de 15 dias e excelência de 80% ainda precisam de calibração SESI. Sem dados verificados, o resultado permanece não apurável.</p></details>
</section>
</template>
