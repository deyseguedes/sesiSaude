<script setup>
import { nextTick, onBeforeUnmount, ref, useId, watch } from 'vue'
import AppIcon from './AppIcon.vue'
const props = defineProps({ open: Boolean, title: String, subtitle: String, busy: Boolean })
const emit = defineEmits(['close'])
const dialog = ref(null), titleId = useId()
watch(() => props.open, async open => {
  await nextTick()
  if (!dialog.value) return
  if (open && !dialog.value.open) dialog.value.showModal()
  if (!open && dialog.value.open) dialog.value.close()
}, { immediate: true })
function cancel(event) { event.preventDefault(); if (!props.busy) emit('close') }
onBeforeUnmount(() => dialog.value?.close())
</script>
<template><dialog ref="dialog" class="side-panel" :aria-labelledby="titleId" @cancel="cancel"><header class="side-panel-header"><div><p class="eyebrow">VIVA+ ELO</p><h2 :id="titleId" class="h4 mb-1">{{ title }}</h2><p v-if="subtitle" class="text-secondary small mb-0">{{ subtitle }}</p></div><button class="icon-button" aria-label="Fechar painel" :disabled="busy" @click="emit('close')"><AppIcon name="close" /></button></header><div class="side-panel-body"><slot /></div></dialog></template>
