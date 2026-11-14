<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth.js'
import { apiRequest } from '../services/api.js'
import FormPanel from '../components/FormPanel.vue'
const auth = useAuthStore(), route = useRoute(), router = useRouter()
const initial = { invitation_token: typeof route.query.invite === 'string' ? route.query.invite : '' }
const busy = ref(false), error = ref(''), success = ref('')
const fields = [
  { name: 'invitation_token', label: 'Código do convite recebido', max: 64, wide: true },
  { name: 'name', label: 'Nome completo', max: 150 }, { name: 'email', label: 'E-mail indicado no convite', type: 'email', max: 190 },
  { name: 'password', label: 'Crie uma senha de 12 a 72 bytes', type: 'password', max: 72 }, { name: 'birth_date', label: 'Data de nascimento', type: 'date' },
  { name: 'contact', label: 'Telefone para contato', type: 'tel', max: 40 }, { name: 'postal_code', label: 'CEP', max: 9 },
  { name: 'preferred_period', label: 'Melhor período para contato', options: [{ value: 'morning', label: 'Manhã' }, { value: 'afternoon', label: 'Tarde' }, { value: 'evening', label: 'Noite' }, { value: 'flexible', label: 'Flexível' }] },
  { name: 'accept_notice', label: 'Li e compreendi o aviso de privacidade abaixo', type: 'checkbox', wide: true },
]
async function submit(data) {
  busy.value = true; error.value = ''
  try { await auth.load(); const result = await apiRequest('/enrollment', 'POST', data); success.value = result.message }
  catch (e) { error.value = e.message } finally { busy.value = false }
}
onMounted(() => { if (route.query.invite) router.replace({ path: '/register', query: {} }) })
</script>
<template><div class="row justify-content-center"><div class="col-lg-8"><p class="eyebrow">INDÚSTRIA, TRABALHADORES E FAMÍLIAS</p><h1>Seu cuidado começa aqui</h1><p class="lead">Cadastre-se pelo convite da indústria ou do titular. Depois da validação do vínculo, escolha atendimento presencial ou por teleconsulta.</p><p class="small text-secondary">Dependentes adultos têm conta própria. Menores são cadastrados pela gestão após verificação do responsável.</p><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><div v-if="success" class="alert alert-success" role="status">{{ success }} <RouterLink to="/login">Ir para o login</RouterLink></div><template v-else><section class="card card-body mb-4"><h2 class="h5">Aviso de privacidade do protótipo</h2><p>Nome, contato e vínculo permitem organizar o acesso aos serviços contratados. A gestão validará seu contato e sua elegibilidade. A empresa não recebe seu prontuário nem o histórico individual de conteúdos educativos. Dependentes adultos mantêm sua própria conta. Localização na busca é opcional e não é acompanhada continuamente.</p><p class="mb-0">Ambiente de Hackathon: informe somente dados fictícios. Para dúvidas sobre o cadastro ou seu vínculo, procure a equipe responsável pelo convite.</p></section><section class="card card-body"><FormPanel :fields="fields" :initial="initial" :busy="busy" submit-label="Enviar cadastro para validação" @submit="submit" /></section></template><RouterLink class="d-inline-block mt-3" to="/login">Já tenho uma conta</RouterLink></div></div></template>
