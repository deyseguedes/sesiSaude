import { defineStore } from 'pinia'
export const roles = { admin: 'Administrador SESI', worker: 'Trabalhador', dependent: 'Dependente', navigator: 'Navegador de Cuidado', manager: 'Gestor empresarial', doctor: 'Médico', nurse: 'Enfermeiro' }
export const managementLevels = { none: 'Assistencial / individual', coordination: 'Coordenação APS', company: 'Gestão empresarial', platform: 'Gestão da plataforma' }
export const useAuthStore = defineStore('auth', {
  state: () => ({ user: null, csrf: '', ready: false }),
  actions: {
    async load() {
      const response = await fetch('/api/v1/auth/session', { credentials: 'same-origin' })
      const body = await response.json()
      if (!response.ok) throw new Error(body.error?.message || 'Não foi possível verificar a sessão.')
      this.user = body.data.user; this.csrf = body.data.csrf_token; this.ready = true
    },
    async login(email, password) {
      await this.load()
      const response = await fetch('/api/v1/auth/login', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.csrf }, body: JSON.stringify({ email, password }) })
      const body = await response.json()
      if (!response.ok) throw new Error(body.error?.message || 'Falha na autenticação.')
      this.user = body.data.user; this.csrf = body.data.csrf_token
    },
    async logout() {
      const response = await fetch('/api/v1/auth/logout', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': this.csrf } })
      const body = await response.json()
      if (!response.ok) throw new Error(body.error?.message || 'Falha ao sair.')
      this.user = null; this.csrf = body.data.csrf_token
    },
  },
})
