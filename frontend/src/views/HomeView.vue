<script setup>
import { onMounted } from 'vue'
import { useSystemStore } from '../stores/system.js'

const system = useSystemStore()
onMounted(() => system.checkHealth())
</script>

<template>
  <h1>Continuidade do cuidado</h1>
  <p class="lead">Base da plataforma VIVA+ ELO — Fase A.</p>
  <section class="card my-4" aria-labelledby="connection-title">
    <div class="card-body">
      <h2 id="connection-title" class="h5">Conexão com a plataforma</h2>
      <div aria-live="polite" role="status">
        <p v-if="system.loading">Verificando conexão…</p>
        <p v-else-if="system.error" class="text-danger">{{ system.error }}</p>
        <p v-else-if="system.health">API conectada: {{ system.health.service }}.</p>
      </div>
      <button class="btn btn-outline-success" :disabled="system.loading" @click="system.checkHealth()">
        Verificar novamente
      </button>
    </div>
  </section>
  <p class="text-secondary">Protótipo de Hackathon. Utilize somente dados fictícios.
    Os módulos assistenciais serão implementados nas próximas fases.</p>
</template>
