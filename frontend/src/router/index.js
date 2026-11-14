import { createRouter, createWebHistory } from 'vue-router'
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
    { path: '/nursing-worklist', component: NursingWorklistView, meta: { auth: true, roles: ['doctor', 'nurse'] } },
    { path: '/care-dashboard', component: DashboardView, meta: { auth: true, roles: ['doctor','nurse'] } },
    { path: '/login', component: LoginView },
    { path: '/register', component: RegisterView },
    { path: '/management', component: ManagementView, meta: { auth: true, management: true } },
    { path: '/campaign-management', component: CampaignManagementView, meta: { auth: true, management: true } },
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
  if (!to.meta.auth) return true
  const auth = useAuthStore()
  try { if (!auth.ready) await auth.load() } catch { return '/login' }
  if (!auth.user) return '/login'
  if (to.meta.roles && !to.meta.roles.includes(auth.user.role)) return '/'
  if (to.meta.management && (!auth.user.management_level || auth.user.management_level === 'none')) return '/'
  return true
})
export default router
