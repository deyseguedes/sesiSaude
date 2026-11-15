<script setup>
import {ref} from 'vue'
import {useAuthStore} from '../stores/auth.js'
import {useRouter} from 'vue-router'
const auth=useAuthStore(),router=useRouter(),busy=ref(false),error=ref('')
async function go(role,path){busy.value=true;try{await auth.demo(role);await router.push(path)}catch(e){error.value=e.message}finally{busy.value=false}}
</script><template><nav class="d-flex flex-wrap gap-2 mb-3" aria-label="Roteiro da banca"><button class="btn btn-sm" :class="auth.user.role==='nurse'?'btn-success':'btn-outline-success'" :disabled="busy" @click="go('nurse','/')">1 · Fila</button><button class="btn btn-sm" :class="$route.path==='/live'?'btn-success':'btn-outline-success'" :disabled="busy" @click="go('nurse','/live')">2 · Celular ao vivo</button><button class="btn btn-sm" :class="auth.user.role==='admin'?'btn-success':'btn-outline-success'" :disabled="busy" @click="go('admin','/')">3 · Resultados e campanhas</button></nav><p v-if="error" class="alert alert-danger">{{error}}</p></template>
