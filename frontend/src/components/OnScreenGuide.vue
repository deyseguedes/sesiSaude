<script setup>
import {computed,nextTick,onBeforeUnmount,onMounted,ref,useId,watch} from 'vue'
import {useRoute} from 'vue-router'
import {useAuthStore} from '../stores/auth.js'
import {guideFor} from '../utils/on-screen-guide.js'
import SidePanel from './SidePanel.vue'
const auth=useAuthStore(),route=useRoute(),dialog=ref(null),card=ref(null),trigger=ref(null),titleId=useId()
const active=ref(false),manual=ref(false),index=ref(0),panel=ref(''),ring=ref(null),position=ref({left:'16px',bottom:'20px'}),missing=ref(false)
const steps=computed(()=>guideFor(auth.user,route.path,panel.value)),current=computed(()=>steps.value[index.value]||steps.value[0])
let interval,previousFocus
function locate(scroll=false){
 if(!active.value)return
 const targets=[...document.querySelectorAll(current.value.target)]
 const target=targets.find(el=>el.getClientRects().length&&getComputedStyle(el).visibility!=='hidden')
 missing.value=!target
 if(!target){ring.value=null;position.value={left:'max(12px, calc(50vw - 190px))',bottom:'max(16px, env(safe-area-inset-bottom))'};return}
 if(scroll)target.scrollIntoView({block:'center',inline:'nearest',behavior:'instant'})
 const r=target.getBoundingClientRect(),width=window.innerWidth,height=window.innerHeight
 const next={left:Math.max(6,r.left-5),top:Math.max(6,r.top-5),width:Math.min(r.width+10,width-Math.max(6,r.left-5)-6),height:Math.min(r.height+10,height-Math.max(6,r.top-5)-6)}
 if(JSON.stringify(next)!==JSON.stringify(ring.value))ring.value=next
 const cardHeight=card.value?.offsetHeight||280,cardWidth=Math.min(390,width-24)
 const top=r.bottom+14+cardHeight<height-12?r.bottom+14:r.top-cardHeight-14>=12?r.top-cardHeight-14:Math.max(12,height-cardHeight-16)
 position.value={left:Math.max(12,Math.min(r.left,width-cardWidth-12))+'px',top:top+'px'}
}
function close(){
 const wasOpen=active.value||dialog.value?.open
 active.value=false;clearInterval(interval);interval=null;ring.value=null
 if(dialog.value?.open)dialog.value.close()
 if(wasOpen&&previousFocus?.isConnected)previousFocus.focus({preventScroll:true})
 previousFocus=null
}
async function start(options={}){
 manual.value=false;panel.value=options.panel||'';index.value=0;previousFocus=document.activeElement;active.value=true
 await nextTick()
 if(!dialog.value.open)dialog.value.showModal()
 locate(true);await nextTick();locate();card.value?.querySelector('button')?.focus({preventScroll:true})
 clearInterval(interval);interval=setInterval(()=>locate(),300)
}
async function move(delta){
 index.value=Math.max(0,Math.min(steps.value.length-1,index.value+delta))
 await nextTick();locate(true);await nextTick();locate()
}
function openManual(){close();manual.value=true}
function panelHelp(event){start({panel:event.detail?.title||''})}
watch(()=>route.fullPath,()=>{close();manual.value=false})
watch(()=>auth.user?.id,()=>{close();manual.value=false})
onMounted(()=>{window.addEventListener('viva:guide',panelHelp);window.addEventListener('resize',locate)})
onBeforeUnmount(()=>{close();window.removeEventListener('viva:guide',panelHelp);window.removeEventListener('resize',locate)})
defineExpose({start,openManual})
</script>
<template>
 <button v-if="!active && !manual" ref="trigger" class="screen-guide-trigger" data-testid="screen-guide-trigger" @click="start()">? <span>Guia na tela</span></button>
 <dialog ref="dialog" class="screen-guide-dialog" :aria-labelledby="titleId" @cancel.prevent="close">
  <div class="screen-guide-surface" @click.self="close">
   <div v-if="ring" class="screen-guide-ring" :style="Object.fromEntries(Object.entries(ring).map(([k,v])=>[k,v+'px']))" aria-hidden="true" data-testid="screen-guide-highlight" />
   <div v-else class="screen-guide-dimmer" aria-hidden="true" />
   <section ref="card" class="screen-guide-card" :style="position" data-testid="screen-guide-card">
    <div class="screen-guide-heading"><span>GUIA DESTA TELA · {{ index+1 }} / {{ steps.length }}</span><button class="screen-guide-close" aria-label="Fechar guia" @click="close">×</button></div>
    <progress :value="index+1" :max="steps.length" aria-label="Progresso do guia" />
    <div aria-live="polite" aria-atomic="true"><h2 :id="titleId">{{ current.title }}</h2><p>{{ current.text }}</p><small v-if="missing" class="screen-guide-note">Esta área pode aparecer depois de carregar dados ou conforme a situação do registro.</small></div>
    <div class="screen-guide-actions"><button class="btn btn-sm btn-outline-secondary" :disabled="index===0" @click="move(-1)">Anterior</button><button class="btn btn-sm btn-success" @click="index===steps.length-1?close():move(1)">{{ index===steps.length-1?'Concluir guia':'Próximo' }}</button></div>
    <button class="screen-guide-manual-link" @click="openManual">Ver manual completo aqui</button>
   </section>
  </div>
 </dialog>
 <SidePanel :open="manual" title="Guia de uso da plataforma" subtitle="Consulte sem sair da tela em que você estava." :guide-help="false" class="screen-guide-manual" @close="manual=false">
  <iframe v-if="manual" src="/guia-de-uso.html?embedded=1" title="Manual completo do VIVA+ ELO" class="screen-guide-frame" />
 </SidePanel>
</template>
<style scoped>
.screen-guide-trigger{position:fixed;right:20px;bottom:22px;z-index:150;border:1px solid #c4d8cc;border-radius:999px;background:#fff;color:#13594d;box-shadow:0 4px 20px #183e3920;font-family:inherit;font-size:14px;line-height:1.3;font-weight:700;padding:12px 18px;display:flex;gap:10px;align-items:center}
.screen-guide-dialog{position:fixed;inset:0;margin:0;border:0;padding:0;width:100vw;max-width:none;height:100dvh;max-height:none;background:transparent;color:#193c36;overflow:hidden}
.screen-guide-dialog::backdrop{background:transparent}
.screen-guide-surface{position:absolute;inset:0}
.screen-guide-ring{position:fixed;border:3px solid #9ee4be;border-radius:10px;box-shadow:0 0 0 9999px #0d2922b5;pointer-events:none}
.screen-guide-dimmer{position:fixed;inset:0;background:#0d2922b5}
.screen-guide-card{position:fixed;width:min(390px,calc(100vw - 24px));max-height:calc(100dvh - 24px);overflow:auto;background:white;border-radius:16px;padding:22px;box-shadow:0 12px 60px #0004}
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
