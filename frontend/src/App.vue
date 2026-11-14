<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore, roles, managementLevels } from './stores/auth.js'
const auth = useAuthStore(), router = useRouter(), error = ref('')
const clinical = computed(() => ['doctor', 'nurse'].includes(auth.user?.role))
const beneficiary = computed(() => ['worker', 'dependent'].includes(auth.user?.role))
const management = computed(() => !!auth.user?.management_level && auth.user.management_level !== 'none')
async function logout() { try { await auth.logout(); await router.push('/login') } catch (e) { error.value = e.message } }
</script>
<template>
  <header class="app-header">
    <div class="container-fluid px-lg-5 d-flex flex-wrap align-items-center justify-content-between gap-3 py-3"><RouterLink to="/" class="brand text-decoration-none">VIVA<span>+</span> ELO<small>INDÚSTRIA · PESSOAS · CONTINUIDADE</small></RouterLink><div v-if="auth.user" class="d-flex align-items-center flex-wrap gap-3"><span class="small text-secondary">{{ auth.user.name }} · {{ roles[auth.user.role] }}<span v-if="management"> · {{ managementLevels[auth.user.management_level] }}</span></span><button class="btn btn-sm btn-outline-secondary" @click="logout">Sair</button></div></div>
    <nav v-if="auth.user" class="app-subnav container-fluid px-lg-5 d-flex flex-wrap gap-3 py-3" aria-label="Navegação principal">
      <RouterLink to="/" class="nav-item-link">Visão geral</RouterLink>
      <RouterLink v-if="beneficiary" to="/find-care" class="nav-item-link">Encontrar atendimento</RouterLink>
      <RouterLink v-if="beneficiary" to="/my-care" class="nav-item-link">Meu cuidado</RouterLink>
      <RouterLink v-if="clinical" to="/patients" class="nav-item-link">Pacientes</RouterLink>
      <RouterLink v-if="clinical" to="/my-agenda" class="nav-item-link">Minha agenda</RouterLink>
      <RouterLink v-if="clinical || auth.user.role === 'navigator'" to="/continuity" class="nav-item-link">ELO Reconecta</RouterLink>
      <RouterLink v-if="clinical || auth.user.role === 'navigator'" to="/engagement" class="nav-item-link">Acolhimento e campanhas</RouterLink>
      <RouterLink v-if="management" to="/management" class="nav-item-link">Gestão</RouterLink>
      <RouterLink v-if="management" to="/campaign-management" class="nav-item-link">Ciclos de campanhas</RouterLink>
      <RouterLink v-if="clinical || management" to="/education-editor" class="nav-item-link">Conteúdos educativos</RouterLink>
    </nav>
  </header>
  <main class="container-fluid px-lg-5 py-4 py-lg-5"><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><RouterView :key="auth.user?.id || 'anonymous'" /></main>
  <footer class="container-fluid px-lg-5 py-4 text-secondary small">VIVA+ ELO · Indústrias, trabalhadores e dependentes. Demonstração com dados fictícios. Decisões clínicas cabem à equipe habilitada.</footer>
</template>
