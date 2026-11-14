<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
const auth = useAuthStore(), router = useRouter()
const email = ref(''), password = ref(''), error = ref(''), busy = ref(false)
async function submit() {
  busy.value = true; error.value = ''
  try { await auth.login(email.value, password.value); password.value = ''; await router.push('/') }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
</script>
<template>
  <div class="row justify-content-center"><div class="col-md-6 col-lg-4">
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
