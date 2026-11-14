<script setup>
import { ref, watch, onBeforeUnmount, nextTick } from 'vue'
import Chart from 'chart.js/auto'
const props = defineProps({ measurements: { type: Array, default: () => [] }, indicator: Object })
const canvas = ref(null)
let chart
watch(() => [props.measurements, props.indicator], async () => {
  await nextTick(); chart?.destroy()
  if (!canvas.value || !props.indicator || props.indicator.type === 'categorical') return
  const rows = props.measurements.filter(m => Number(m.indicator_id) === Number(props.indicator.id)).sort((a, b) => a.collected_at.localeCompare(b.collected_at) || a.id - b.id)
  chart = new Chart(canvas.value, {
    type: 'line',
    data: { datasets: ['professional', 'self_reported'].map((source, index) => ({ label: source === 'professional' ? 'Medição profissional' : 'Autorrelato', data: rows.filter(m => m.source === source).map(m => ({ x: m.collected_at, y: Number(m.value_num) })), borderColor: index ? '#b56e16' : '#087f6d', backgroundColor: index ? '#b56e16' : '#087f6d', borderDash: index ? [5, 5] : [], tension: 0.15 })) },
    options: { responsive: true, maintainAspectRatio: false, scales: { x: { type: 'category', labels: [...new Set(rows.map(m => m.collected_at))], title: { display: true, text: 'Coleta · horário de Brasília' } }, y: { title: { display: true, text: props.indicator.unit } } } },
  })
}, { immediate: true, deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>
<template><div v-if="indicator?.type !== 'categorical'" style="height: 300px"><canvas ref="canvas" role="img" :aria-label="`Evolução de ${indicator?.name || 'indicador'}. Valores disponíveis na tabela.`" /></div><p v-else class="text-secondary">Consulte a sequência de categorias na tabela de medições.</p></template>
