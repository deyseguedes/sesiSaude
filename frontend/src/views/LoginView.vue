<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { roles } from '../stores/auth.js'
import { shareDemo } from '../services/share-demo.js'
import AppIcon from '../components/AppIcon.vue'
const auth = useAuthStore(), router = useRouter()
const email = ref(''), password = ref(''), error = ref(''), busy = ref(false)
const profiles = [
  { role: 'admin', icon: 'building', detail: 'Indústrias, contratos, equipes e níveis de gestão.' },
  { role: 'manager', icon: 'building', detail: 'Visão empresarial, trabalhadores e campanhas.' },
  { role: 'doctor', icon: 'heart', detail: 'Pacientes, resumo do prontuário e planos de cuidado.' },
  { role: 'nurse', icon: 'people', detail: 'Coordenação APS, acompanhamento e agenda.' },
  { role: 'navigator', icon: 'cycle', detail: 'Acolhimento, barreiras de acesso e continuidade.' },
  { role: 'worker', icon: 'people', detail: 'Atendimentos, conteúdos e campanhas de saúde.' },
  { role: 'dependent', icon: 'heart', detail: 'Espaço individual de cuidado para a família.' },
]
async function enterProfile(role) {
  busy.value = true; error.value = ''
  try { await auth.demo(role); await router.push('/') } catch (e) { error.value = e.message } finally { busy.value = false }
}
async function submit() {
  busy.value = true; error.value = ''
  try { await auth.login(email.value, password.value); password.value = ''; await router.push('/') }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
</script>
<template>
  <section v-if="shareDemo" class="demo-entry">
    <p class="eyebrow">CONHEÇA A PLATAFORMA</p><h1>Um cuidado que conecta<br>indústria, pessoas e famílias.</h1>
    <p class="text-secondary fs-5 mt-3">Escolha um perfil e explore seu espaço. Acesso direto, sem senha.</p>
    <p class="demo-disclosure"><strong>Demonstração com dados fictícios.</strong> Consulta das telas e dos níveis de acesso; alterações e agendamentos não são gravados.</p>
    <p v-if="error" role="alert" class="alert alert-danger">{{ error }}</p>
    <div class="demo-profiles"><button v-for="p in profiles" :key="p.role" class="demo-profile" :disabled="busy" @click="enterProfile(p.role)"><span class="action-icon"><AppIcon :name="p.icon" /></span><span><strong>{{ roles[p.role] }}</strong><small>{{ p.detail }}</small></span><AppIcon name="arrow" :size="18" /></button></div>
    <p class="small text-secondary mt-4">Para experimentar outro nível, use “Trocar perfil” no topo da plataforma.</p>
  </section>
  <div v-else class="row justify-content-center"><div class="col-md-6 col-lg-4">
    <p class="text-success fw-semibold">VIVA+ ELO · APS</p><h1 class="h2">Entre no seu espaço</h1>
    <p class="text-secondary">Acompanhar, acolher e reconectar.</p>
    <form class="card card-body mt-4" @submit.prevent="submit">
      <label for="email" class="form-label">E-mail</label><input id="email" v-model="email" class="form-control mb-3" type="email" autocomplete="username" required maxlength="190">
      <label for="password" class="form-label">Senha</label><input id="password" v-model="password" class="form-control mb-3" type="password" autocomplete="current-password" required maxlength="72">
      <p v-if="error" role="alert" class="text-danger">{{ error }}</p>
      <button class="btn btn-success" :disabled="busy">{{ busy ? 'Entrando…' : 'Entrar' }}</button>
    </form><p class="mt-3"><RouterLink to="/register">Recebi um convite para cadastro</RouterLink></p><p class="small text-secondary mt-3">Ambiente de demonstração. Somente dados fictícios.</p>
  </div></div>
</template>
