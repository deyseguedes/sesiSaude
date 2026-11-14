import { defineStore } from 'pinia'
import { requestJson } from '../services/http.js'
export const roles = { admin: 'Administrador SESI', worker: 'Trabalhador', dependent: 'Dependente', navigator: 'Navegador de Cuidado', manager: 'Gestor empresarial', doctor: 'Médico', nurse: 'Enfermeiro' }
export const managementLevels = { none: 'Assistencial / individual', coordination: 'Coordenação APS', company: 'Gestão empresarial', platform: 'Gestão da plataforma' }
export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null, csrf: '', ready: false }),
  actions: {
    async load() {
      const body = await requestJson('/api/v1/auth/session')
      this.user = body.data.user; this.csrf = body.data.csrf_token; this.ready = true
    },
    async login(email, password) {
      await this.load()
      const body = await requestJson('/api/v1/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf }, body: JSON.stringify({ email, password }) })
      this.user = body.data.user; this.csrf = body.data.csrf_token
    },
    async logout() {
      const body = await requestJson('/api/v1/auth/logout', { method: 'POST', headers: { 'X-CSRF-Token': this.csrf } })
      this.user = null; this.csrf = body.data.csrf_token
    },
  },
})
