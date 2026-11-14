<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore, roles } from './stores/auth.js'
import { useWorkspaceStore } from './stores/workspace.js'
import { navigationFor, normalizeSearch } from './utils/workflow.js'
import AppIcon from './components/AppIcon.vue'
import SidePanel from './components/SidePanel.vue'
import { shareDemo } from './services/share-demo.js'
const auth = useAuthStore(), workspace = useWorkspaceStore(), router = useRouter(), route = useRoute()
const error = ref(''), menuOpen = ref(false), commandsOpen = ref(false), search = ref('')
const navigation = computed(() => navigationFor(auth.user))
const commands = computed(() => navigation.value.actions.filter(a => normalizeSearch(a.label + ' ' + a.detail).includes(normalizeSearch(search.value))))
const currentTitle = computed(() => [...navigation.value.primary, ...navigation.value.secondary].find(item => item.to !== '/' && route.path.startsWith(item.to))?.label || (route.path === '/' ? 'Início' : 'Meu espaço'))
const initials = computed(() => auth.user?.name.split(' — ')[0].split(' ').filter(Boolean).slice(0, 2).map(s => s[0]).join(''))
async function logout() { try { await auth.logout(); workspace.$reset(); menuOpen.value = false; commandsOpen.value = false; await router.push('/login') } catch (e) { error.value = e.message } }
function command(action) { commandsOpen.value = false; router.push(action.to) }
function keyboard(event) { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && auth.user) { event.preventDefault(); commandsOpen.value = !commandsOpen.value } }
watch(() => route.fullPath, () => { menuOpen.value = false; error.value = '' })
watch(commandsOpen, () => { search.value = '' })
onMounted(() => window.addEventListener('keydown', keyboard))
onBeforeUnmount(() => window.removeEventListener('keydown', keyboard))
</script>
<template>
  <a class="skip-link" href="#main-content">Ir para o conteúdo</a>
  <template v-if="auth.user">
    <aside class="app-sidebar">
      <RouterLink to="/" class="brand text-decoration-none">VIVA<span>+</span> ELO<small>CONTINUIDADE DO CUIDADO</small></RouterLink>
      <p class="sidebar-label">MEU ESPAÇO</p>
      <nav aria-label="Navegação principal" class="sidebar-links"><RouterLink v-for="item in navigation.primary" :key="item.to" :to="item.to" :class="{ 'is-active': route.path === item.to || item.to !== '/' && route.path.startsWith(item.to + '/') }" class="sidebar-link"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></RouterLink></nav>
      <template v-if="navigation.secondary.length"><p class="sidebar-label mt-4">EQUIPE E ORGANIZAÇÃO</p><nav aria-label="Equipe e organização" class="sidebar-links"><RouterLink v-for="item in navigation.secondary" :key="item.to" :to="item.to" :class="{ 'is-active': route.path === item.to || item.to !== '/' && route.path.startsWith(item.to + '/') }" class="sidebar-link"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></RouterLink></nav></template>
      <div class="sidebar-foot"><span class="care-dot" /> Cuidado que continua.<p>Indústria, pessoas e famílias.</p></div>
    </aside>
    <div class="app-workspace">
      <header class="workspace-header"><div class="d-flex align-items-center gap-3"><button class="icon-button mobile-menu-button" aria-label="Abrir navegação" @click="menuOpen = true"><AppIcon name="menu" /></button><span class="workspace-title">{{ currentTitle }}</span></div><button class="command-trigger" @click="commandsOpen = true"><AppIcon name="search" /><span>O que você precisa fazer?</span><kbd>Ctrl K</kbd></button><div class="user-summary"><span class="avatar avatar-small">{{ initials }}</span><div><strong>{{ auth.user.name.split(' — ')[0] }}</strong><small>{{ roles[auth.user.role] }}</small></div><button class="btn btn-sm btn-link text-secondary" @click="logout">Sair</button></div></header>
      <main id="main-content" class="workspace-content" tabindex="-1"><div v-if="shareDemo" class="demo-disclosure mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2"><span><strong>Demonstração · {{ roles[auth.user.role] }}</strong><br>Dados fictícios · somente consulta · sem gravação de alterações.</span><button class="btn btn-sm btn-outline-success" @click="logout">Trocar perfil</button></div><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><RouterView :key="auth.user.id" /></main>
      <footer class="workspace-footer">VIVA+ ELO · Demonstração com dados fictícios. Decisões clínicas cabem à equipe habilitada.</footer>
    </div>
    <nav class="mobile-bottom-nav" aria-label="Acesso rápido"><RouterLink v-for="item in navigation.primary.slice(0, 4)" :key="item.to" :to="item.to"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></RouterLink><button @click="menuOpen = true"><AppIcon name="menu" /><span>Mais</span></button></nav>
    <SidePanel :open="menuOpen" title="Seu espaço" @close="menuOpen = false"><nav class="sidebar-links" aria-label="Navegação no celular"><RouterLink v-for="item in [...navigation.primary, ...navigation.secondary]" :key="item.to" :to="item.to" :class="{ 'is-active': route.path === item.to || item.to !== '/' && route.path.startsWith(item.to + '/') }" class="sidebar-link"><AppIcon :name="item.icon" />{{ item.label }}</RouterLink></nav><button class="btn btn-outline-secondary mt-4" @click="logout">Sair da conta</button></SidePanel>
    <SidePanel :open="commandsOpen" title="O que você precisa fazer?" subtitle="Ações disponíveis para o seu perfil." @close="commandsOpen = false"><label for="quick-command" class="visually-hidden">Buscar ação</label><input id="quick-command" v-model="search" class="form-control form-control-lg mb-4" type="search" placeholder="Ex.: convidar, paciente, agenda…" autofocus><button v-for="action in commands" :key="action.to" class="command-result" @click="command(action)"><span class="action-icon"><AppIcon :name="action.icon" /></span><span><strong>{{ action.label }}</strong><small>{{ action.detail }}</small></span><AppIcon name="arrow" /></button><p v-if="!commands.length" class="text-secondary">Nenhuma ação encontrada para essa busca.</p></SidePanel>
  </template>
  <template v-else><header class="guest-header"><RouterLink to="/" class="brand text-decoration-none">VIVA<span>+</span> ELO<small>INDÚSTRIA · PESSOAS · CUIDADO</small></RouterLink></header><main id="main-content" class="container py-4 py-lg-5"><RouterView /></main></template>
</template>
