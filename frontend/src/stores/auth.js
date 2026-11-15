import { defineStore } from 'pinia'
import { requestJson } from '../services/http.js'
import { shareDemo } from '../services/share-demo.js'
export const roles = { leader: 'Líder de setor', admin: 'Administrador SESI', worker: 'Trabalhador', dependent: 'Dependente', navigator: 'Navegador de Cuidado', manager: 'Gestor empresarial', doctor: 'Médico', nurse: 'Enfermeiro' }
export const managementLevels = { none: 'Assistencial / individual', coordination: 'Coordenação APS', company: 'Gestão empresarial', platform: 'Gestão da plataforma' }
export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null, csrf: '', ready: false, localProfiles: [], publicDemo: false, sessionGeneration:0 }),
  actions: {
    async demo(role) {
      this.sessionGeneration++
      await this.load()
      const body = await requestJson(shareDemo ? '/api/v1/auth/demo' : '/api/v1/auth/local-profile', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf }, body: JSON.stringify({ role }) })
      this.user = body.data.user; this.csrf = body.data.csrf_token; this.ready = true
    },
    async load() {
      const body = await requestJson('/api/v1/auth/session')
      this.user = body.data.user; this.csrf = body.data.csrf_token; this.ready = true
      this.localProfiles = body.data.local_profiles || []
      this.publicDemo = body.data.public_demo === true
    },
    async login(email, password) {
      this.sessionGeneration++
      await this.load()
      const body = await requestJson('/api/v1/auth/login', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf }, body: JSON.stringify({ email, password }) })
      this.user = body.data.user; this.csrf = body.data.csrf_token
    },
    async logout() {
      this.sessionGeneration++
      const body = await requestJson('/api/v1/auth/logout', { method: 'POST', headers: { 'X-CSRF-Token': this.csrf } })
      this.user = null; this.csrf = body.data.csrf_token
    },
  },
})
