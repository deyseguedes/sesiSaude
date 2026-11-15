<script setup>
import {onBeforeUnmount,onMounted,ref} from 'vue'
import {download} from '../utils/download.js'
import {apiGet} from '../services/api.js'
import {formatarData} from '../utils/format.js'
const props=defineProps({compact:Boolean,refreshToken:Number})
const data=ref(null),error=ref('');let timer,running=false
async function exportCsv(){try{const r=await apiGet('/demo/outreach/export');download(r.csv,'retornos-aps.csv')}catch(e){error.value=e.message}}
async function load(){if(running)return;running=true;try{data.value=await apiGet('/demo/outreach/queue');error.value=''}catch(e){error.value=e.message}finally{running=false}}
onMounted(()=>{load();timer=setInterval(load,2000);window.addEventListener('elo-channel-updated',load)})
onBeforeUnmount(()=>{clearInterval(timer);window.removeEventListener('elo-channel-updated',load)})
</script>
<template><section class="card card-body mb-3" data-demo="convocation-queue"><p class="eyebrow">SIMULAÇÃO · ATUALIZA A CADA 2 SEGUNDOS</p><h2 class="h4">Convocação sem resposta</h2><button class="btn btn-sm btn-outline-success align-self-start mb-2" @click="exportCsv">Exportar para o sistema da APS (Klingo ou SOC/S+)</button><small class="text-secondary mb-2">Layout a ajustar ao sistema do SESI.</small><p v-if="error" class="alert alert-danger">{{error}}</p><p v-if="data&&!data.items.length">Nenhuma convocação aguardando ação.</p><article v-for="p in (compact?data?.items.slice(0,5):data?.items)" :key="p.person_id" class="border-top py-2" :data-demo="'fila-item-'+p.registration"><div class="d-flex justify-content-between gap-2"><strong>{{p.name}} <small v-if="p.pending_count">+ {{p.pending_count}} pendências</small></strong><span>{{formatarData(p.return_date)}}</span></div><p class="small mb-1">{{p.unit}} · {{p.reply==='cannot'?'Não pode ir · '+p.why.split('dificuldade voluntária: ')[1]:'Confirmar ou pactuar novo horário'}}</p><details :data-demo="'porque-'+p.registration"><summary class="small">Por que aparece nesta posição?</summary><p class="small mb-0">{{p.why}}</p></details></article><details v-if="data?.waiting.length" class="mt-3"><summary>Aguardando resposta, ainda no prazo · {{data.waiting.length}}</summary><p v-for="p in data.waiting" :key="p.person_id" class="small mt-2">{{p.name}} · mensagem há {{p.message_age}} dias</p></details></section></template>
