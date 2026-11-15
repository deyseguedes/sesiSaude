<script setup>
import {useAuthStore} from '../stores/auth.js'
const auth=useAuthStore()
import WorkerJourney from '../components/WorkerJourney.vue'
import MarcosPhone from '../components/MarcosPhone.vue'
import SemesterEligibility from '../components/SemesterEligibility.vue'
</script><template><p class="eyebrow">UM TOQUE PARA CONTINUAR</p><h1 class="h3">Seu retorno, do seu jeito</h1><MarcosPhone/><RouterLink to="/accessible-channel" class="btn btn-link mt-3">Outros exemplos: Carla e Seu José →</RouterLink><div v-if="auth.user.role==='worker'" class="row g-3 mt-3"><div class="col-lg-6"><WorkerJourney/></div><div class="col-lg-6"><SemesterEligibility/></div></div></template>
