<script setup>
import {useAuthStore} from '../stores/auth.js'
import CampaignExperimentsView from './CampaignExperimentsView.vue'
import CycleManagementView from './CycleManagementView.vue'
const auth=useAuthStore()
</script><template><template v-if="auth.publicDemo"><CampaignExperimentsView/><details v-if="['admin','manager'].includes(auth.user.role)" class="card card-body mt-3"><summary>Ciclos educativos existentes · Meu primeiro passo e novos ciclos</summary><CycleManagementView class="mt-3"/></details></template><CycleManagementView v-else/></template>