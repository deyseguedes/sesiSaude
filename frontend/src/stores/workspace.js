import { defineStore } from 'pinia'
import { apiGet } from '../services/api.js'

export const useWorkspaceStore = defineStore('workspace', {
  state: () => ({ userId: null, companyId: null, companies: [] }),
  actions: {
    async ensure(user, force = false) {
      if (this.userId !== Number(user.id)) { this.$reset(); this.userId = Number(user.id); this.companyId = Number(user.company_id) }
      if (!force && this.companies.length) return
      this.companies = await apiGet('/management/companies')
      if (!this.companies.some(c => Number(c.id) === Number(this.companyId))) this.companyId = this.companies[0]?.id || null
    },
  },
})
