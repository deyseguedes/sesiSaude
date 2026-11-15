<script setup>
import {ref,watch} from 'vue'
import SidePanel from './SidePanel.vue'
import {readAccessibility,saveAccessibility,applyAccessibility,defaultAccessibility} from '../utils/accessibility.js'
const open=ref(false),settings=ref(readAccessibility()),saved=ref(true)
watch(settings,value=>{applyAccessibility(document.documentElement,value);saved.value=saveAccessibility(value)},{deep:true,immediate:true})
const options=[['contrast','Alto contraste','Fundo escuro, texto claro e contornos destacados.'],['spacing','Mais espaço para leitura','Aumenta o espaço entre linhas, letras e palavras.'],['links','Destacar links','Sublinha os links para facilitar sua identificação.'],['reducedMotion','Reduzir animações','Desativa transições e movimentos da interface.']]
</script>
<template>
 <button type="button" class="accessibility-trigger" data-testid="accessibility-trigger" aria-haspopup="dialog" aria-controls="life-accessibility-panel" :aria-expanded="open" @click="open=true">Acessibilidade</button>
 <SidePanel id="life-accessibility-panel" :open="open" title="Acessibilidade" subtitle="Ajuste a leitura e a navegação do Life+." :guide-help="false" @close="open=false">
  <fieldset class="mb-4"><legend class="h6">Tamanho do texto</legend><div class="d-flex flex-wrap gap-2" role="group" aria-label="Tamanho do texto"><button v-for="size in [100,125,150]" :key="size" type="button" class="btn btn-outline-success" :aria-pressed="settings.textSize===size" :class="{'a11y-selected':settings.textSize===size}" @click="settings.textSize=size">{{size}}%</button></div></fieldset>
  <label v-for="[key,title,description] in options" :key="key" class="accessibility-option"><input v-model="settings[key]" type="checkbox" class="form-check-input" :aria-describedby="'a11y-help-'+key"><span><strong>{{title}}</strong><small :id="'a11y-help-'+key">{{description}}</small></span></label>
  <p class="small text-secondary mt-3">A preferência de movimento reduzido do seu dispositivo também é respeitada. Você pode usar Tab para navegar e Esc para fechar este painel.</p>
  <p v-if="!saved" role="status" class="small">Os ajustes estão ativos, mas este navegador não permitiu salvá-los.</p>
  <button type="button" class="btn btn-outline-secondary mt-2" @click="settings=defaultAccessibility()">Restaurar ajustes padrão</button>
 </SidePanel>
</template>
