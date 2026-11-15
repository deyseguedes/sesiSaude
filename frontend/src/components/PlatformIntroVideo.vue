<script setup>
import {ref,useId,onBeforeUnmount} from 'vue'
defineProps({headingLevel:{type:String,default:'h2'}})
const player=ref(null),failed=ref(false),titleId=useId()
function pauseOthers(){for(const video of document.querySelectorAll('video[data-platform-intro]'))if(video!==player.value)video.pause()}
onBeforeUnmount(()=>player.value?.pause())
</script>
<template>
 <section class="platform-intro-video" :aria-labelledby="titleId">
  <component :is="headingLevel" :id="titleId" class="h5">Conheça o Life+ em vídeo</component>
  <p class="text-secondary">Assista à apresentação explicativa da plataforma.</p>
  <video ref="player" data-platform-intro controls playsinline preload="none" poster="/media/life-video-poster.svg" aria-label="Vídeo explicativo da plataforma Life+" @play="pauseOthers" @error="failed=true"><source src="/media/life-apresentacao.mp4" type="video/mp4">Seu navegador não reproduz este vídeo. <a href="/media/life-apresentacao.mp4">Abrir o vídeo explicativo</a>.</video>
  <p v-if="failed" role="status" class="mt-2">Não foi possível carregar o vídeo. <a href="/media/life-apresentacao.mp4" target="_blank" rel="noopener">Abrir o vídeo diretamente</a>.</p>
  <details class="mt-3"><summary>Resumo da plataforma em texto</summary><p class="mt-2 mb-0">O Life+ conecta trabalhadores das indústrias, dependentes e equipes de Atenção Primária à Saúde. A plataforma organiza consultas, retornos e acompanhamento do cuidado, com acesso por perfil para trabalhadores, profissionais e gestão.</p></details>
 </section>
</template>
<style scoped>
.platform-intro-video video{display:block;width:100%;max-height:65dvh;aspect-ratio:16/9;object-fit:contain;background:#000;border-radius:12px}
.platform-intro-video summary{cursor:pointer}
</style>
