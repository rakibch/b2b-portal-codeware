import { createRouter, createWebHistory } from 'vue-router'
import { i18n } from '@/i18n'
import { useAuthStore } from '@/stores/auth'
import { decideNavigation } from './guards'

const AuthLayout = () => import('@/layouts/AuthLayout.vue')
const PortalLayout = () => import('@/layouts/PortalLayout.vue')

/** Feature route modules register themselves here (buyer portal, admin portal). */
const portalChildren = [
  {
    path: 'account',
    name: 'account',
    component: () => import('@/views/account/AccountView.vue'),
    meta: { titleKey: 'account.title' },
  },
  {
    path: 'forbidden',
    name: 'forbidden',
    component: () => import('@/views/errors/ForbiddenView.vue'),
    meta: { titleKey: 'errors.forbidden_title' },
  },
]

// Portal first: both layouts sit at '/', and '/' itself must resolve to the portal 'home' route.
export const routes = [
  {
    path: '/',
    name: 'portal',
    component: PortalLayout,
    children: [{ path: '', name: 'home', component: { render: () => null }, meta: { home: true } }, ...portalChildren],
  },
  {
    path: '/',
    component: AuthLayout,
    children: [
      { path: 'login', name: 'login', component: () => import('@/views/auth/LoginView.vue'), meta: { guest: true, titleKey: 'auth.sign_in' } },
      { path: 'forgot-password', name: 'forgot-password', component: () => import('@/views/auth/ForgotPasswordView.vue'), meta: { guest: true, titleKey: 'auth.forgot_title' } },
      { path: 'reset-password', name: 'reset-password', component: () => import('@/views/auth/ResetPasswordView.vue'), meta: { public: true, titleKey: 'auth.reset_title' } },
      { path: 'register', name: 'register', component: () => import('@/views/auth/RegisterView.vue'), meta: { guest: true, titleKey: 'register.title' } },
      { path: 'accept-invitation', name: 'accept-invitation', component: () => import('@/views/auth/AcceptInvitationView.vue'), meta: { guest: true, titleKey: 'team.accept' } },
    ],
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    component: () => import('@/views/errors/NotFoundView.vue'),
    meta: { public: true, titleKey: 'errors.not_found_title' },
  },
]

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior(to, from, saved) {
    if (saved) return saved
    if (to.hash) return { el: to.hash, behavior: 'smooth' }
    if (to.path === from.path) return false
    return { top: 0 }
  },
})

/**
 * Adds child routes from a feature module (buyer / admin portal) under the portal layout.
 * Must run before app.mount() so the initial navigation can resolve them.
 */
export function registerPortalRoutes(children) {
  for (const route of children) router.addRoute('portal', route)
}

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()
  return decideNavigation(to, auth, (name) => router.hasRoute(name))
})

router.afterEach((to) => {
  const app = i18n.global.t('app.name')
  const key = [...to.matched].reverse().find((r) => r.meta?.titleKey)?.meta.titleKey
  document.title = key ? `${i18n.global.t(key)} · ${app}` : app
})
