<script setup>
import { reactive, watch, useId } from 'vue'
const props = defineProps({ fields: { type: Array, required: true }, initial: { type: Object, default: () => ({}) }, busy: Boolean, submitLabel: { type: String, default: 'Salvar registro' } })
const emit = defineEmits(['submit'])
const values = reactive({}), prefix = useId()
watch(() => props.initial, () => {
  for (const key of Object.keys(values)) delete values[key]
  for (const field of props.fields) values[field.name] = props.initial[field.name] ?? field.default ?? (field.type === 'checkbox' ? false : field.type === 'checkbox_group' ? [] : '')
}, { immediate: true })
function submit() {
  const result = {}
  for (const field of props.fields) {
    const v = values[field.name]
    result[field.name] = field.type === 'number' ? (v === '' || v === null ? null : Number(v)) : v
  }
  emit('submit', result)
}
</script>
<template>
  <form class="row g-3" @submit.prevent="submit">
    <div v-for="field in fields" :key="field.name" :class="field.wide ? 'col-12' : 'col-md-6'">
      <label v-if="field.type !== 'checkbox_group'" :for="`${prefix}-${field.name}`" class="form-label">{{ field.label }}</label>
      <fieldset v-if="field.type === 'checkbox_group'"><legend class="form-label fs-6">{{ field.label }}</legend><label v-for="o in field.options" :key="o.value" class="d-block mb-2"><input v-model="values[field.name]" :value="o.value" class="form-check-input me-2" type="checkbox" :disabled="busy">{{ o.label }}</label></fieldset>
      <select v-else-if="field.options" :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-select" :required="!field.optional" :disabled="busy">
        <option value="" disabled>Selecione</option><option v-for="option in field.options" :key="option.value" :value="option.value">{{ option.label }}</option>
      </select>
      <textarea v-else-if="field.type === 'textarea'" :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-control" rows="3" :maxlength="field.max || 4000" :required="!field.optional" :disabled="busy" />
      <input v-else-if="field.type === 'checkbox'" :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-check-input d-block" type="checkbox" :disabled="busy">
      <input v-else :id="`${prefix}-${field.name}`" v-model="values[field.name]" class="form-control" :type="field.type || 'text'" :autocomplete="field.type === 'password' ? 'new-password' : undefined" :step="field.type === 'number' ? 'any' : undefined" :min="field.min" :maxlength="field.max || 500" :required="!field.optional" :disabled="busy">
      <div v-if="field.help" class="form-text">{{ field.help }}</div>
    </div>
    <div class="col-12"><button class="btn btn-success" :disabled="busy">{{ busy ? 'Salvando…' : submitLabel }}</button></div>
  </form>
</template>
