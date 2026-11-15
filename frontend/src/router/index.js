import { createRouter, createWebHistory } from 'vue-router'
import WorkerChannelView from '../views/WorkerChannelView.vue'
import SecurityView from '../views/SecurityView.vue'
import MarcosView from '../views/MarcosView.vue'
import LiveView from '../views/LiveView.vue'
import ImportView from '../views/ImportView.vue'
import DemoEntryView from '../views/DemoEntryView.vue'
import {apiRequest} from '../services/api.js'
import HomeView from '../views/HomeView.vue'
import NotFoundView from '../views/NotFoundView.vue'
import LoginView from '../views/LoginView.vue'
import WorkspaceHome from '../views/WorkspaceHome.vue'
import PatientsView from '../views/PatientsView.vue'
import PatientView from '../views/PatientView.vue'
import ContinuityView from '../views/ContinuityView.vue'
import RegisterView from '../views/RegisterView.vue'
import ManagementView from '../views/ManagementView.vue'
import FindCareView from '../views/FindCareView.vue'
import LearningView from '../views/LearningView.vue'
import EducationEditorView from '../views/EducationEditorView.vue'
import CampaignManagementView from '../views/CampaignManagementView.vue'
import EngagementView from '../views/EngagementView.vue'
import ProfessionalAgendaView from '../views/ProfessionalAgendaView.vue'
import NursingWorklistView from '../views/NursingWorklistView.vue'
import DashboardView from '../views/DashboardView.vue'
import { useAuthStore } from '../stores/auth.js'

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(to, from, saved) { return saved || (to.path === from.path ? false : { top: 0 }) },
  routes: [
 {path:'/entrar/:profile',component:DemoEntryView},
 {path:'/privacy',component:SecurityView,meta:{auth:true}},
 {path:'/accessible-channel',component:WorkerChannelView,meta:{auth:true,roles:['worker','admin','doctor','nurse','navigator']}},
 {path:'/live',component:LiveView,meta:{auth:true,roles:['admin','nurse','doctor']}},
 {path:'/import',component:ImportView,meta:{auth:true,roles:['admin','nurse']}},
    { path: '/worker-channel', component: MarcosView, meta: { auth: true, roles: ['worker','admin','doctor','nurse','navigator'] } },
    { path: '/security', component: SecurityView, meta: { auth: true } },
    { path: '/nursing-worklist', component: NursingWorklistView, meta: { auth: true, roles: ['doctor', 'nurse'] } },
    { path: '/care-dashboard', component: DashboardView, meta: { auth: true, roles: ['doctor','nurse'] } },
    { path: '/login', component: LoginView },
    { path: '/register', component: RegisterView },
    { path: '/management', component: ManagementView, meta: { auth: true, management: true } },
    { path: '/campaign-management', component: CampaignManagementView, meta: { auth: true, roles:['admin','manager','nurse'] } },
    { path: '/campaign-management/:id', component: CampaignManagementView, meta: { auth: true, roles:['admin','manager','nurse'] } },
    { path: '/education-editor', component: EducationEditorView, meta: { auth: true, roles: ['admin', 'manager', 'doctor', 'nurse'] } },
    { path: '/find-care', component: FindCareView, meta: { auth: true, roles: ['worker', 'dependent'] } },
    { path: '/my-care', component: LearningView, meta: { auth: true, roles: ['worker', 'dependent'] } },
    { path: '/engagement', component: EngagementView, meta: { auth: true, roles: ['doctor', 'nurse', 'navigator'] } },
    { path: '/my-agenda', component: ProfessionalAgendaView, meta: { auth: true, roles: ['doctor', 'nurse'] } },
    { path: '/', component: WorkspaceHome, meta: { auth: true } },
    { path: '/patients', component: PatientsView, meta: { auth: true, roles: ['doctor', 'nurse'] } },
    { path: '/patients/:id', component: PatientView, meta: { auth: true, roles: ['doctor', 'nurse'] } },
    { path: '/continuity', component: ContinuityView, meta: { auth: true, roles: ['doctor', 'nurse', 'navigator'] } },
    { path: '/system', component: HomeView },
    { path: '/:pathMatch(.*)*', component: NotFoundView },
  ],
})
router.beforeEach(async (to) => {
 const auth=useAuthStore()
 if(to.query.gravacao==='0'){sessionStorage.removeItem('elo-recording');sessionStorage.removeItem('elo-demo-clock')}
 if(to.query.gravacao==='1')sessionStorage.setItem('elo-recording','1')
 document.body.classList.toggle('demo-recording',sessionStorage.getItem('elo-recording')==='1')
 if(to.path.startsWith('/entrar/')){
  try{
   await auth.load();const role=({'admin-sesi':'admin','gestor-empresa':'manager',medico:'doctor',enfermeiro:'nurse',navegador:'navigator',trabalhador:'worker',dependente:'dependent',lider:'leader'})[to.params.profile]
   if(!role||!auth.localProfiles.includes(role))return {path:'/login',query:{entrada:'indisponivel'}}
   if(auth.user?.role!==role)await auth.demo(role)
   if(to.query.restaurar==='1'&&auth.publicDemo)await apiRequest('/demo/reset','POST')
   if(to.query.gravacao==='1'&&auth.publicDemo){const r=await apiRequest('/demo/recording','POST');sessionStorage.setItem('elo-demo-clock',r.clock)}
   let path=typeof to.query.ir==='string'?to.query.ir:'/'
   if(!path.startsWith('/')||path.startsWith('//')||path.includes('\\\\')||path.startsWith('/entrar'))path='/'
   let target=router.resolve(path);if(target.matched.some(r=>r.path.includes('pathMatch')))target=router.resolve('/')
   return {path:target.path,query:{...target.query,...(to.query.gravacao==='1'?{gravacao:'1'}:{})},replace:true}
  }catch{return {path:'/login',query:{entrada:'indisponivel'}}}
 }
 if(!to.meta.auth)return true
 try{if(!auth.ready)await auth.load()}catch{return '/login'}
 if(!auth.user&&auth.publicDemo&&(to.query.restaurar==='1'||to.query.gravacao==='1')){
  const role=to.path.startsWith('/worker-channel')||to.path.startsWith('/find-care')||to.path.startsWith('/my-care')?'worker':to.path.startsWith('/campaign-management')||to.path.startsWith('/management')||to.path==='/import'?'admin':'nurse'
  await auth.demo(role)
 }
 if(!auth.user)return '/login'
 if(auth.publicDemo&&to.query.gravacao==='1'&&!sessionStorage.getItem('elo-demo-clock')){const r=await apiRequest('/demo/recording','POST');sessionStorage.setItem('elo-demo-clock',r.clock)}
 if(to.meta.roles&&!to.meta.roles.includes(auth.user.role))return '/'
 if(to.meta.management&&(!['admin','manager'].includes(auth.user.role)||!auth.user.management_level||auth.user.management_level==='none'))return '/'
 if(auth.publicDemo&&to.query.restaurar==='1'){await apiRequest('/demo/reset','POST');if(to.query.gravacao==='1'){const r=await apiRequest('/demo/recording','POST');sessionStorage.setItem('elo-demo-clock',r.clock)}const query={...to.query};delete query.restaurar;return {path:to.path,query,replace:true}}
 return true
})
export default router
