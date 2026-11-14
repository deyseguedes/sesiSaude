import { defineStore } from 'pinia'
import { apiGet } from '../services/api.js'

export const useSystemStore = defineStore('system', {
  state: () => ({ health: null, loading: false, error: '' }),
  actions: {
    async checkHealth() {
      this.loading = true
      this.error = ''
      this.health = null
      try {
        this.health = await apiGet('/health')
      } catch {
        this.error = 'Não foi possível conectar à API. Verifique se o backend está em execução.'
      } finally {
        this.loading = false
      }
    },
  },
})
