<script setup>
import {APP_NAME,APP_SUBTITLE} from './config.js'
import JuryNavigation from './components/JuryNavigation.vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore, roles } from './stores/auth.js'
import { useWorkspaceStore } from './stores/workspace.js'
import { navigationFor, normalizeSearch } from './utils/workflow.js'
import AppIcon from './components/AppIcon.vue'
import SidePanel from './components/SidePanel.vue'
import OnScreenGuide from './components/OnScreenGuide.vue'
import AccessibilityControls from './components/AccessibilityControls.vue'
import PlatformIntroVideo from './components/PlatformIntroVideo.vue'
import { shareDemo } from './services/share-demo.js'
const auth = useAuthStore(), workspace = useWorkspaceStore(), router = useRouter(), route = useRoute()
const recording=computed(()=>route.query.gravacao==='1'||sessionStorage.getItem('elo-recording')==='1')
const screenGuide=ref(null),introOpen=ref(false)
function showGuide(){ menuOpen.value=false; commandsOpen.value=false; screenGuide.value?.start() }
async function resetDemo(){try{await (await import('./services/api.js')).apiRequest('/demo/reset','POST');location.reload()}catch(e){error.value=e.message}}
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
async function updatePage(focus=true){await nextTick();const main=document.getElementById('main-content');const heading=main?.querySelector('h1');document.title=(heading?.textContent?.trim()||currentTitle.value)+' | '+APP_NAME;if(focus)main?.focus({preventScroll:true})}
watch(()=>route.path,()=>updatePage())
onMounted(()=>updatePage(false))
onMounted(() => window.addEventListener('keydown', keyboard))
onBeforeUnmount(() => window.removeEventListener('keydown', keyboard))
</script>
<template>
  <a class="skip-link" href="#main-content">Ir para o conteúdo</a>
  <template v-if="auth.user">
    <aside class="app-sidebar">
      <RouterLink to="/" class="brand text-decoration-none">{{APP_NAME}}<small>{{APP_SUBTITLE}}</small></RouterLink>
      <p class="sidebar-label">MEU ESPAÇO</p>
      <nav aria-label="Navegação principal" class="sidebar-links"><RouterLink v-for="item in navigation.primary" :key="item.to" :to="item.to" :class="{ 'is-active': route.path === item.to || item.to !== '/' && route.path.startsWith(item.to + '/') }" class="sidebar-link"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></RouterLink></nav>
      <template v-if="navigation.secondary.length"><p class="sidebar-label mt-4">EQUIPE E ORGANIZAÇÃO</p><nav aria-label="Equipe e organização" class="sidebar-links"><RouterLink v-for="item in navigation.secondary" :key="item.to" :to="item.to" :class="{ 'is-active': route.path === item.to || item.to !== '/' && route.path.startsWith(item.to + '/') }" class="sidebar-link"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></RouterLink></nav></template>
      <div class="sidebar-foot"><span class="care-dot" /> Cuidado que continua.<p>Indústria, pessoas e famílias.</p></div>
    </aside>
    <div class="app-workspace">
      <header class="workspace-header"><div class="d-flex align-items-center gap-3"><button class="icon-button mobile-menu-button" aria-label="Abrir navegação" @click="menuOpen = true"><AppIcon name="menu" /></button><span class="workspace-title">{{ currentTitle }}</span></div><button class="command-trigger" @click="commandsOpen = true"><AppIcon name="search" /><span>O que você precisa fazer?</span><kbd>Ctrl K</kbd></button><div class="user-summary"><span class="avatar avatar-small">{{ initials }}</span><div><strong>{{ auth.user.name.split(' — ')[0] }}</strong><small>{{ roles[auth.user.role] }}</small></div><button class="btn btn-sm btn-link text-secondary" @click="logout">Sair</button></div></header>
      <main id="main-content" class="workspace-content" tabindex="-1"><div v-if="shareDemo" class="demo-disclosure mb-4 d-flex flex-wrap align-items-center justify-content-between gap-2"><span><strong>Demonstração · {{ roles[auth.user.role] }}</strong><br>Dados fictícios · somente consulta · sem gravação de alterações.</span><button class="btn btn-sm btn-outline-success" @click="logout">Trocar perfil</button></div><div v-if="auth.publicDemo&&!recording" class="demo-disclosure mb-4 d-flex flex-wrap justify-content-between gap-2"><span><strong>Demonstração interativa · dados fictícios</strong><br>O roteiro é individual por visita. Cadastros legados são compartilhados.</span><div><button class="btn btn-sm btn-outline-success me-2" @click="resetDemo">Restaurar demonstração</button><button class="btn btn-sm btn-outline-success" @click="logout">Trocar perfil</button></div></div><p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p><span v-if="auth.publicDemo&&recording" class="recording-seal">Demonstração · dados fictícios · Simulação</span><JuryNavigation v-if="auth.publicDemo&&!recording" /><RouterView :key="auth.user.id" /></main>
      <footer class="workspace-footer">{{APP_NAME}} · Demonstração com dados fictícios. Decisões clínicas cabem à equipe habilitada. <button type="button" class="btn btn-link btn-sm p-0" @click="showGuide">Guia na tela</button> · <button type="button" class="btn btn-link btn-sm p-0" @click="introOpen=true">Vídeo da plataforma</button> · <RouterLink to="/privacy" data-demo="privacidade">Privacidade e LGPD</RouterLink></footer>
    </div>
    <nav class="mobile-bottom-nav" aria-label="Acesso rápido"><RouterLink v-for="item in navigation.primary.slice(0, 4)" :key="item.to" :to="item.to"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></RouterLink><button @click="menuOpen = true"><AppIcon name="menu" /><span>Mais</span></button></nav>
    <SidePanel :open="menuOpen" title="Seu espaço" @close="menuOpen = false"><nav class="sidebar-links" aria-label="Navegação no celular"><RouterLink v-for="item in [...navigation.primary, ...navigation.secondary]" :key="item.to" :to="item.to" :class="{ 'is-active': route.path === item.to || item.to !== '/' && route.path.startsWith(item.to + '/') }" class="sidebar-link"><AppIcon :name="item.icon" />{{ item.label }}</RouterLink><button class="sidebar-link border-0 w-100 text-start bg-transparent" @click="showGuide">Guia na tela</button></nav><button class="btn btn-outline-secondary mt-4" @click="logout">Sair da conta</button></SidePanel>
    <SidePanel :open="commandsOpen" title="O que você precisa fazer?" subtitle="Ações disponíveis para o seu perfil." @close="commandsOpen = false"><label for="quick-command" class="visually-hidden">Buscar ação</label><input id="quick-command" v-model="search" class="form-control form-control-lg mb-4" type="search" placeholder="Ex.: convidar, paciente, agenda…" autofocus><button v-for="action in commands" :key="action.to" class="command-result" @click="command(action)"><span class="action-icon"><AppIcon :name="action.icon" /></span><span><strong>{{ action.label }}</strong><small>{{ action.detail }}</small></span><AppIcon name="arrow" /></button><p v-if="!commands.length" class="text-secondary">Nenhuma ação encontrada para essa busca.</p></SidePanel>
  </template>
  <template v-else><header class="guest-header"><RouterLink to="/" class="brand text-decoration-none">{{APP_NAME}}<small>{{APP_SUBTITLE}}</small></RouterLink><button class="btn btn-sm btn-outline-success" @click="showGuide">Guia na tela</button></header><main id="main-content" class="container py-4 py-lg-5" tabindex="-1"><RouterView /></main><footer class="container py-3"><button type="button" class="btn btn-link btn-sm p-0 me-3" @click="introOpen=true">Vídeo da plataforma</button><RouterLink to="/privacy">Privacidade e LGPD</RouterLink></footer></template>
  <OnScreenGuide v-if="!recording" ref="screenGuide" @intro="introOpen=true" />
  <SidePanel :open="introOpen" title="Apresentação do Life+" :guide-help="false" @close="introOpen=false"><PlatformIntroVideo v-if="introOpen" heading-level="h3" /></SidePanel>
  <AccessibilityControls />
</template>
