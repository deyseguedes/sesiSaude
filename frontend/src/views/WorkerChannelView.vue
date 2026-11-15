<script setup>
import SemesterEligibility from '../components/SemesterEligibility.vue'
import {computed,onBeforeUnmount,onMounted,ref} from 'vue'
import {apiGet,apiRequest} from '../services/api.js'
import {useAuthStore} from '../stores/auth.js'
import {formatDateTime} from '../utils/workflow.js'
const auth=useAuthStore(),data=ref(null),selected=ref(''),error=ref(''),notice=ref(''),busy=ref(false),audioReady=ref(false),leaderConsent=ref(false)
const person=computed(()=>data.value?.people.find(p=>String(p.id)===String(selected.value)))
const message=computed(()=>person.value?'Seu retorno está marcado para '+formatDateTime(person.value.scheduled_at)+'. Você consegue ir? Se precisar, nossa equipe ajuda.':'')
function speak(text){if(!('speechSynthesis' in window)){error.value='A leitura em voz alta não está disponível neste navegador. Os botões continuam disponíveis; peça apoio à equipe.';return}speechSynthesis.cancel();const utterance=new SpeechSynthesisUtterance(text);utterance.lang='pt-BR';utterance.rate=0.85;speechSynthesis.speak(utterance)}
async function load(){data.value=await apiGet('/demo/channel');if(!selected.value)selected.value=data.value.people[0]?.id||''}
async function reply(choice){if(busy.value)return;busy.value=true;error.value='';notice.value='';try{const result=await apiRequest('/demo/channel/reply','POST',{patient_id:Number(selected.value),choice,leader_consent:leaderConsent.value});notice.value=result.message;window.dispatchEvent(new Event('elo-channel-updated'));if('BroadcastChannel' in window){const channel=new BroadcastChannel('elo-demo-channel');channel.postMessage('updated');channel.close()}await load()}catch(e){error.value=e.message}finally{busy.value=false}}
function listenReply(){audioReady.value=true;speak('Bom dia. Eu não consigo ir nesse horário. Pode me ligar para combinar outro horário?')}
onMounted(async()=>{if(auth.publicDemo)try{await load()}catch(e){error.value=e.message}})
onBeforeUnmount(()=>{if('speechSynthesis' in window)speechSynthesis.cancel()})
</script>
<template>
<p class="eyebrow">CANAL ACESSÍVEL DO TRABALHADOR</p><h1>Mensagens da sua equipe</h1>
<details class="alert alert-info"><summary>WhatsApp simulado · apenas dados fictícios</summary><p class="mt-2 mb-0">Não envia WhatsApp, não grava microfone e não realiza ligação real. A resposta escolhida atualiza o banco demonstrativo e a fila da enfermagem.</p></details>
<p v-if="!auth.publicDemo">Este cenário funciona apenas na demonstração com dados fictícios.</p>
<template v-else><label for="channel-person" class="form-label">Experimentar com</label><select id="channel-person" class="form-select mb-3" v-model="selected" @change="notice='';audioReady=false;leaderConsent=false"><option v-for="p in data?.people" :key="p.id" :value="p.id">{{p.display_name}} · {{p.display_name==='Carla'?'responde pelos botões':'prefere ouvir e responder por áudio'}}</option></select>
<p v-if="error" class="alert alert-danger" role="alert">{{error}}</p><p v-if="notice" class="alert alert-success" role="status">{{notice}}</p>
<section v-if="person" class="phone-preview mx-auto" data-testid="worker-channel"><header class="p-3 text-white rounded-top" style="background:#075e54"><strong>Equipe de cuidado · SESI</strong><div class="small">Conversa demonstrativa</div></header><div class="p-3" style="background:#ece5dd"><p class="bg-white p-3 rounded">{{message}}</p><button type="button" class="btn btn-light w-100 mb-3" @click="speak(message)">▶ Ouvir a mensagem</button>
<label class="form-check bg-white rounded p-3 ps-5 mb-3"><input class="form-check-input" type="checkbox" v-model="leaderConsent"><span class="form-check-label">Autorizo mostrar somente meu nome e horário ao líder neste cenário fictício.</span></label><div class="d-grid gap-2"><button class="btn btn-success btn-lg" :disabled="busy" @click="reply('confirm')">✓ Eu vou</button><button class="btn btn-light btn-lg" :disabled="busy" @click="reply('reschedule')">↻ Preciso de outro horário</button><button class="btn btn-light btn-lg" :disabled="busy" @click="reply('help')">♡ Preciso de ajuda</button><button class="btn btn-light btn-lg" :disabled="busy" @click="reply('call')">☎ Quero que me liguem</button></div>
<section v-if="person.display_name==='Seu José'" class="bg-white rounded p-3 mt-3"><h2 class="h6">Resposta por áudio de Seu José</h2><button class="btn btn-outline-success w-100 mb-2" @click="listenReply">▶ Ouvir áudio de exemplo · 8 s aprox.</button><p class="small">“Não consigo ir nesse horário. Pode me ligar para combinar outro horário?”</p><button class="btn btn-success w-100" :disabled="busy" @click="reply('audio_help')">Enviar este áudio simulado</button><small class="d-block mt-2">A fala é sintetizada pelo navegador. O pedido tem conteúdo conhecido; não há transcrição automática.</small></section>
<p class="small mt-3 mb-0">Nenhuma informação sobre diagnóstico ou risco é enviada nesta mensagem.</p></div></section>
<section v-if="person?.responses.length" class="card card-body mt-4"><h2 class="h5">Respostas recebidas</h2><p v-for="(r,index) in person.responses" :key="index">{{formatDateTime(r.created_at)}} · {{({confirm:'Confirmou por botão',reschedule:'Pediu outro horário',help:'Pediu ajuda',audio_help:'Enviou áudio simulado: precisa de contato',call:'Pediu ligação'})[r.payload.choice]}}</p></section>
<SemesterEligibility v-if="auth.user?.role==='worker'" class="mt-4" /><RouterLink v-if="['nurse','doctor'].includes(auth.user?.role)" to="/nursing-worklist" class="btn btn-outline-success mt-4">Ver resposta na fila da enfermagem →</RouterLink></template>
</template>
<style scoped>.phone-preview{max-width:390px;border:8px solid #243d3c;border-radius:24px;overflow:hidden}#channel-person{max-width:620px}</style>
