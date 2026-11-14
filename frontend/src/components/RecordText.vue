<script setup>
import { computed, ref, watch } from 'vue'
const props = defineProps({ text: { type: String, default: '' }, limit: { type: Number, default: 240 }, empty: { type: String, default: 'Sem registro.' } })
const expanded = ref(false)
const characters = computed(() => Array.from(props.text || ''))
const truncated = computed(() => characters.value.length > props.limit)
const excerpt = computed(() => characters.value.slice(0, props.limit).join(''))
watch(() => props.text, () => { expanded.value = false })
</script>
<template><div class="record-text"><p v-if="!text?.trim()" class="text-secondary mb-0">{{ empty }}</p><template v-else><small v-if="truncated && !expanded" class="record-excerpt-label">Trecho do registro</small><p class="mb-1">{{ truncated && !expanded ? excerpt + '…' : text }}</p><button v-if="truncated" class="btn btn-link btn-sm p-0" type="button" :aria-expanded="expanded" @click="expanded = !expanded">{{ expanded ? 'Recolher texto' : 'Ler registro completo' }}</button></template></div></template>
