<script setup>
import { computed, reactive, ref, watch, useId } from 'vue'
import { initialFieldValue } from '../utils/workflow.js'
import { shareDemo } from '../services/share-demo.js'
const props = defineProps({ fields: { type: Array, required: true }, initial: { type: Object, default: () => ({}) }, busy: Boolean, submitLabel: { type: String, default: 'Salvar registro' }, submitNextLabel: String })
const emit = defineEmits(['submit', 'submit-next', 'change'])
const values = reactive({}), prefix = useId(), advancedOpen = ref(false)
const visibleFields = computed(() => props.fields.filter(field => !field.when || field.when(values)).map(field => ({ ...field, options: typeof field.options === 'function' ? field.options(values) : field.options })))
watch(() => props.initial, () => {
  for (const key of Object.keys(values)) delete values[key]
  for (const field of props.fields) values[field.name] = initialFieldValue(field, props.initial)
}, { immediate: true })
watch(visibleFields, fields => {
  for (const field of fields) {
    if (!Object.hasOwn(values, field.name)) values[field.name] = initialFieldValue(field)
    if (field.options && field.type !== 'checkbox_group') {
      if (values[field.name] !== '' && values[field.name] !== null && !field.options.some(o => o.value === values[field.name])) values[field.name] = ''
      if (values[field.name] === '' && !field.optional && !field.noAutoSelect && field.options.length === 1) values[field.name] = field.options[0].value
    }
  }
}, { immediate: true })
watch(values, () => emit('change', { ...values }))
function submit(next=false) {
  if (shareDemo) return
  const result = {}
  for (const field of visibleFields.value) {
    const v = values[field.name]
    result[field.name] = field.type === 'number' ? (v === '' || v === null ? null : Number(v)) : v
  }
  emit(next ? 'submit-next' : 'submit', result)
}
</script>
<template>
  <form class="row g-3 workflow-form" @submit.prevent="submit($event.submitter?.value === 'next')" @keydown.ctrl.enter.prevent="$event.currentTarget.requestSubmit()">
    <div v-for="field in visibleFields" v-show="!field.advanced || advancedOpen" :key="field.name" :class="field.wide || field.type === 'checkbox' ? 'col-12' : 'col-md-6'">
      <label v-if="!['checkbox_group', 'checkbox'].includes(field.type)" :for="`${prefix}-${field.name}`" class="form-label">{{ field.label }}<span v-if="field.optional" class="optional-label">opcional</span></label>
      <fieldset v-if="field.type === 'checkbox_group'"><legend class="form-label fs-6">{{ field.label }}</legend><label v-for="o in field.options" :key="o.value" class="d-block mb-2"><input v-model="values[field.name]" :value="o.value" class="form-check-input me-2" type="checkbox" :disabled="busy || shareDemo">{{ o.label }}</label></fieldset>
      <select v-else-if="field.options" :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-select" :required="!field.optional" :disabled="busy || shareDemo">
        <option value="" :disabled="!field.optional">{{ field.optional ? 'Não informar' : 'Selecione' }}</option><option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <textarea v-else-if="field.type === 'textarea'" :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-control" rows="3" :maxlength="field.max || 4000" :required="!field.optional" :disabled="busy || shareDemo" />
      <label v-else-if="field.type === 'checkbox'" :for="`${prefix}-${field.name}`" class="check-row"><input :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-check-input" type="checkbox" :required="field.mustCheck" :disabled="busy || shareDemo"><span>{{ field.label }}</span></label>
      <input v-else :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-control" :type="field.type || 'text'" :autocomplete="field.type === 'password' ? 'new-password' : undefined" :step="field.type === 'number' ? 'any' : undefined" :min="field.min" :maxlength="field.max || 500" :required="!field.optional" :disabled="busy || shareDemo">
      <div v-if="field.help" class="form-text">{{ field.help }}</div>
    </div>
    <div v-if="visibleFields.some(f => f.advanced)" class="col-12"><button class="btn btn-link p-0" type="button" :aria-expanded="advancedOpen" @click="advancedOpen = !advancedOpen">{{ advancedOpen ? 'Ocultar opções complementares' : 'Opções complementares' }}</button></div>
    <p v-if="shareDemo" class="col-12 small text-secondary mb-0" role="note">Demonstração somente para consulta. Preenchimento e gravação estão desabilitados.</p><div class="col-12 form-actions"><button class="btn btn-success" :disabled="busy || shareDemo">{{ busy ? 'Salvando…' : submitLabel }}</button><button v-if="submitNextLabel" type="submit" value="next" class="btn btn-outline-success" :disabled="busy || shareDemo">{{ submitNextLabel }}</button><small v-if="!shareDemo" class="text-secondary keyboard-hint">Ctrl + Enter para salvar</small></div>
  </form>
</template>
