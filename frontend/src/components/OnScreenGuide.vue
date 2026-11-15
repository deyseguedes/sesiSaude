<script setup>
import {computed,nextTick,onBeforeUnmount,onMounted,ref,shallowRef,useId,watch} from 'vue'
import {useRoute,useRouter} from 'vue-router'
import {navigationFor} from '../utils/workflow.js'
import {useAuthStore} from '../stores/auth.js'
import {guideFor} from '../utils/on-screen-guide.js'
import {findGuideTarget,readGuideContext,openGuideTarget,focusGuideTarget} from '../utils/guide-dom.js'
import SidePanel from './SidePanel.vue'
const auth=useAuthStore(),route=useRoute(),router=useRouter(),dialog=ref(null),card=ref(null),trigger=ref(null),titleId=useId(),host=shallowRef(document.body)
const active=ref(false),manual=ref(false),index=ref(0),panel=ref(''),context=ref({}),ring=ref(null),position=ref({left:'16px',bottom:'20px'}),missing=ref(false),actionReady=ref(false)
const screens=computed(()=>{if(!auth.user)return[{to:'/login',label:'Escolher perfil'},{to:'/system',label:'Status da conexão'}];const n=navigationFor(auth.user);const list=[...n.primary,...n.secondary];if(auth.publicDemo&&['worker','admin','doctor','nurse','navigator'].includes(auth.user.role))list.push({to:'/worker-channel',label:'Canal do trabalhador'});if(['doctor','nurse'].includes(auth.user.role))list.push({to:'/nursing-worklist',label:'Fila de acompanhamento'},{to:'/care-dashboard',label:'Indicadores assistenciais'});return list.filter((s,i,a)=>a.findIndex(v=>v.to===s.to)===i)})
function navigate(event){if(screens.value.some(s=>s.to===event.target.value)){preferredForm=null;router.push(event.target.value)}}
const steps=computed(()=>guideFor(auth.user,route.path==='/'&&auth.publicDemo&&auth.user?.role==='worker'?'/worker-channel':route.path,panel.value,context.value)),current=computed(()=>steps.value[index.value]||steps.value[0])
let interval,previousFocus,preferredForm=null,signature='',syncing=false
function locate(scroll=false){
 if(!active.value)return
 const target=findGuideTarget(current.value.target)
 missing.value=!target;actionReady.value=!!current.value.action&&!!findGuideTarget(current.value.action.target)
 if(!target){ring.value=null;position.value={left:'max(12px, calc(50vw - 190px))',bottom:'max(16px, env(safe-area-inset-bottom))'};return}
 if(scroll)target.scrollIntoView({block:'center',inline:'nearest',behavior:'instant'})
 const r=target.getBoundingClientRect(),width=window.innerWidth,height=window.innerHeight
 const left=Math.max(6,r.left-5),topEdge=Math.max(6,r.top-5)
 const next={left,top:topEdge,width:Math.max(0,Math.min(r.width+10,width-left-6)),height:Math.max(0,Math.min(r.height+10,height-topEdge-6))}
 if(JSON.stringify(next)!==JSON.stringify(ring.value))ring.value=next
 const cardHeight=card.value?.offsetHeight||300,cardWidth=Math.min(390,width-24)
 const top=r.bottom+14+cardHeight<height-12?r.bottom+14:r.top-cardHeight-14>=12?r.top-cardHeight-14:Math.max(12,height-cardHeight-16)
 position.value={left:Math.max(12,Math.min(r.left,width-cardWidth-12))+'px',top:top+'px'}
}
async function syncContext(reset=false){
 if(!active.value||syncing)return
 syncing=true
 try{
  await nextTick()
  const found=readGuideContext(preferredForm),nextSignature=route.fullPath+':'+JSON.stringify(found.context),nextHost=found.panel||document.body
  const changed=reset||signature!==nextSignature,oldTarget=current.value.target,oldForm=context.value.form?.id
  if(host.value!==nextHost){if(dialog.value?.open)dialog.value.close();host.value=nextHost}
  panel.value=found.panel?.querySelector('h2')?.textContent.trim()||''
  context.value=found.context;signature=nextSignature
  if(changed){const retained=oldForm&&oldForm===found.context.form?.id?steps.value.findIndex(s=>s.target===oldTarget):-1;index.value=retained>=0?retained:0}
  await nextTick()
  if(active.value&&!dialog.value?.open)dialog.value?.show()
  locate(changed);await nextTick();locate()
 }finally{syncing=false}
}
function close(){
 const wasOpen=active.value||dialog.value?.open
 active.value=false;clearInterval(interval);interval=null;ring.value=null
 if(dialog.value?.open)dialog.value.close()
 host.value=document.body
 if(wasOpen&&previousFocus?.isConnected)previousFocus.focus({preventScroll:true})
 previousFocus=null
}
async function start(){
 manual.value=false;signature='';index.value=0;previousFocus=document.activeElement;active.value=true
 await syncContext(true)
 card.value?.querySelector('button')?.focus({preventScroll:true})
 clearInterval(interval);interval=setInterval(()=>syncContext(),300)
}
async function move(delta){index.value=Math.max(0,Math.min(steps.value.length-1,index.value+delta));await nextTick();locate(true);await nextTick();locate()}
async function interact(){
 const action=current.value.action;if(!action)return
 if(action.kind==='focus')focusGuideTarget(action.target)
 if(action.kind==='open')openGuideTarget(action.target)
 await nextTick();await syncContext();locate()
}
function openManual(){close();manual.value=true}
function panelHelp(){start()}
function follow(event){
 if(!(event.target instanceof Element)||event.target.closest('[data-guide-ui]')||manual.value)return
 preferredForm=event.target.closest('form')
 if(active.value)syncContext()
}
function keyboard(event){
 if(event.key==='Escape'&&active.value){event.preventDefault();event.stopImmediatePropagation();close()}
}
function resized(){locate(false)}
watch(()=>route.fullPath,()=>{preferredForm=null;manual.value=false;if(active.value)syncContext(true)})
watch(()=>auth.user?.id,()=>{preferredForm=null;if(active.value)syncContext(true)})
onMounted(()=>{window.addEventListener('viva:guide',panelHelp);window.addEventListener('resize',resized);window.addEventListener('keydown',keyboard,true);document.addEventListener('click',follow);document.addEventListener('focusin',follow)})
onBeforeUnmount(()=>{close();window.removeEventListener('viva:guide',panelHelp);window.removeEventListener('resize',resized);window.removeEventListener('keydown',keyboard,true);document.removeEventListener('click',follow);document.removeEventListener('focusin',follow)})
defineExpose({start,openManual})
</script>
<template>
 <button data-guide-ui v-if="!active && !manual" ref="trigger" class="screen-guide-trigger" data-testid="screen-guide-trigger" @click="start()">? <span>Guia na tela</span></button>
 <Teleport :to="host"><dialog data-guide-ui ref="dialog" class="screen-guide-dialog" :aria-labelledby="titleId" @cancel.prevent="close">
  <div class="screen-guide-surface">
   <div v-if="ring" class="screen-guide-ring" :style="Object.fromEntries(Object.entries(ring).map(([k,v])=>[k,v+'px']))" aria-hidden="true" data-testid="screen-guide-highlight" />
   <div v-else class="screen-guide-dimmer" aria-hidden="true" />
   <section ref="card" class="screen-guide-card" :style="position" data-testid="screen-guide-card">
    <div class="screen-guide-heading"><span>{{ context.form ? 'CAMPO A CAMPO' : 'GUIA DESTA TELA' }} · {{ index+1 }} / {{ steps.length }}</span><button class="screen-guide-close" aria-label="Fechar guia" @click="close">×</button></div>
    <progress :value="index+1" :max="steps.length" aria-label="Progresso do guia" />
    <div aria-live="polite" aria-atomic="true"><h2 :id="titleId">{{ current.title }}</h2><p>{{ current.text }}</p><small v-if="missing" class="screen-guide-note">Esta área pode aparecer depois de carregar dados ou conforme a situação do registro.</small></div>
    <p class="screen-guide-note mb-2">Você pode clicar e preencher a tela com o guia aberto.</p><button v-if="current.action" class="btn btn-sm btn-outline-success w-100 mb-2" :disabled="!actionReady" data-testid="screen-guide-interact" @click="interact">{{ current.action.label }}</button>
    <div class="screen-guide-actions"><button class="btn btn-sm btn-outline-secondary" :disabled="index===0" @click="move(-1)">Anterior</button><button class="btn btn-sm btn-success" @click="index===steps.length-1?close():move(1)">{{ index===steps.length-1?'Concluir guia':'Próximo' }}</button></div>
    <label class="screen-guide-note mt-3" for="guide-screen">Explorar outra tela</label><select id="guide-screen" :value="route.path" class="form-select form-select-sm mt-1" aria-label="Escolher tela do guia" @change="navigate"><option v-if="!screens.some(s=>s.to===route.path)" :value="route.path">Tela atual</option><option v-for="s in screens" :key="s.to" :value="s.to">{{ s.label }}</option></select>
    <button class="screen-guide-manual-link" @click="openManual">Ver manual completo aqui</button>
   </section>
  </div>
 </dialog></Teleport>
 <SidePanel :open="manual" title="Guia de uso da plataforma" subtitle="Consulte sem sair da tela em que você estava." :guide-help="false" class="screen-guide-manual" @close="manual=false">
  <iframe v-if="manual" src="/guia-de-uso.html?embedded=1" title="Manual completo do Life+" class="screen-guide-frame" />
 </SidePanel>
</template>
<style scoped>
.screen-guide-trigger{position:fixed;right:20px;bottom:22px;z-index:150;border:1px solid #c4d8cc;border-radius:999px;background:#fff;color:#13594d;box-shadow:0 4px 20px #183e3920;font-family:inherit;font-size:14px;line-height:1.3;font-weight:700;padding:12px 18px;display:flex;gap:10px;align-items:center}
.screen-guide-dialog{position:fixed;inset:0;margin:0;border:0;padding:0;width:100vw;max-width:none;height:100dvh;max-height:none;background:transparent;color:#193c36;overflow:hidden;pointer-events:none;z-index:1600}
.screen-guide-dialog::backdrop{background:transparent}
.screen-guide-surface{position:absolute;inset:0}
.screen-guide-ring{position:fixed;border:3px solid #9ee4be;border-radius:10px;box-shadow:0 0 0 9999px #0d2922b5;pointer-events:none}
.screen-guide-dimmer{position:fixed;inset:0;background:#0d2922b5}
.screen-guide-card{pointer-events:auto;position:fixed;width:min(390px,calc(100vw - 24px));max-height:calc(100dvh - 24px);overflow:auto;background:white;border-radius:16px;padding:22px;box-shadow:0 12px 60px #0004}
.screen-guide-heading{display:flex;justify-content:space-between;align-items:center;gap:10px;color:#356957;font-size:10px;letter-spacing:.07em;font-weight:800}
.screen-guide-close{border:0;background:none;font-size:27px;line-height:1;color:#466359;padding:0 2px}
.screen-guide-card h2{font-size:21px;line-height:1.25;margin:15px 0 10px;letter-spacing:0}
.screen-guide-card p{font-size:14px;line-height:1.6;margin:0 0 12px;color:#425d54}
.screen-guide-card progress{width:100%;height:5px;accent-color:#14734f;margin-top:8px}
.screen-guide-actions{display:flex;justify-content:space-between;gap:12px;margin-top:18px}
.screen-guide-manual-link{display:block;width:100%;border:0;background:transparent;color:#126345;font-size:12px;text-decoration:underline;margin-top:16px;padding:4px}
.screen-guide-note{display:block;font-size:11px;color:#687e77}
.screen-guide-manual{width:min(960px,100%)}
.screen-guide-manual :deep(.side-panel-body){padding:0}
.screen-guide-frame{display:block;width:100%;height:calc(100dvh - 130px);min-height:320px;border:0;background:#f4f7f6}
@media(max-width:760px){.screen-guide-trigger{bottom:calc(82px + env(safe-area-inset-bottom));right:12px;padding:10px 14px;font-size:12px}.screen-guide-card{padding:19px}.screen-guide-card h2{font-size:19px}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important}}
</style>
