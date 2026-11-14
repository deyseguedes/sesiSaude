<script setup>
import { computed, onMounted, ref } from 'vue'
import { apiGet } from '../services/api.js'
const patients = ref([]), search = ref(''), error = ref(''), busy = ref(true)
const filtered = computed(() => patients.value.filter(p => p.name.toLocaleLowerCase('pt-BR').includes(search.value.toLocaleLowerCase('pt-BR'))))
onMounted(async () => { try { patients.value = await apiGet('/clinical/patients') } catch (e) { error.value = e.message } finally { busy.value = false } })
</script>
<template><p class="eyebrow">EQUIPE APS</p><h1>Pacientes acompanhados</h1><p class="text-secondary">Somente pessoas com vínculo assistencial autorizado.</p><label for="patient-search" class="form-label">Buscar por nome</label><input id="patient-search" v-model="search" class="form-control mb-4" placeholder="Nome do paciente"><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><p v-if="busy">Carregando…</p><div class="row g-3"><div v-for="p in filtered" :key="p.id" class="col-md-6 col-lg-4"><RouterLink :to="`/patients/${p.id}`" class="card card-body text-decoration-none patient-link"><span class="avatar mb-3">{{ p.name.slice(0, 1) }}</span><h2 class="h5">{{ p.name }}</h2><span class="text-secondary">Abrir acompanhamento →</span></RouterLink></div></div><p v-if="!busy && !filtered.length">Nenhum paciente encontrado.</p></template>
