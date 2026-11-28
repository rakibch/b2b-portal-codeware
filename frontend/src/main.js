import { createApp, watch } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { i18n } from './i18n'
import { router } from './router'
import { configureHttp } from './api/http'
import { useAuthStore } from './stores/auth'
import { useCartStore } from './stores/cart'
import { useLocaleStore } from './stores/locale'
import { useNotificationStore } from './stores/notifications'
import { useToastStore } from './stores/toast'
import { safeRedirect } from './utils/safeRedirect'
import './assets/main.css'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(i18n)

const auth = useAuthStore()
const toast = useToastStore()
useLocaleStore() // applies <html lang> + fonts immediately

configureHttp({
  getToken: () => auth.token,
  onUnauthenticated: () => {
    const wasSignedIn = auth.isAuthenticated
    auth.clear()
    if (wasSignedIn) toast.warning(i18n.global.t('auth.session_expired'))
    const current = router.currentRoute.value
    if (!current.meta?.guest && !current.meta?.public) {
      router.replace({ name: 'login', query: { redirect: safeRedirect(current.fullPath, '/') } })
    }
  },
  onForbidden: async (error) => {
    toast.error(error.message)
    // KYC status may have changed server-side (approved / suspended) — refresh the session view.
    if (error.code === 'kyc_not_approved' && auth.isAuthenticated) {
      await auth.fetchMe().catch(() => {})
    }
  },
  onError: (error) => toast.error(error.message),
})

// Session-scoped background work: notification polling, announcements and cart.
watch(
  () => auth.user?.id,
  (id) => {
    const notifications = useNotificationStore()
    const cart = useCartStore()
    if (id) {
      notifications.startPolling()
      notifications.loadAnnouncements()
      if (auth.isBuyer && auth.canTransact) cart.fetch({ force: true }).catch(() => {})
    } else {
      notifications.reset()
      cart.reset()
    }
  },
)

// Server-provided labels (statuses, announcements, price sources) are localised by the API via
// Accept-Language, so refresh session-level data when the language changes.
watch(
  () => i18n.global.locale.value,
  () => {
    if (!auth.isAuthenticated) return
    auth.fetchMe().catch(() => {})
    useNotificationStore().loadAnnouncements()
    const cart = useCartStore()
    if (cart.loaded) cart.fetch({ force: true }).catch(() => {})
  },
)

app.use(router)

router.isReady().then(() => app.mount('#app'))

export { app, router }
