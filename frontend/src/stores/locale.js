import { defineStore } from 'pinia'
import { ref } from 'vue'
import { applyLocale, i18n, LOCALE_STORAGE_KEY, SUPPORTED_LOCALES } from '@/i18n'
import { authApi } from '@/api/auth'
import { local } from '@/utils/storage'
import { useAuthStore } from './auth'

export const useLocaleStore = defineStore('locale', () => {
  const locale = ref(i18n.global.locale.value)

  /**
   * Instant switch: updates vue-i18n, <html lang> (fonts), localStorage and — when signed in —
   * the user's saved preference (used for emails, invoices and challans).
   */
  function setLocale(next, { persistRemote = true } = {}) {
    if (!SUPPORTED_LOCALES.includes(next)) return
    locale.value = applyLocale(next)
    local.set(LOCALE_STORAGE_KEY, next)

    const auth = useAuthStore()
    if (persistRemote && auth.isAuthenticated && auth.user?.locale !== next) {
      auth.user.locale = next
      authApi.updateLocale(next).catch(() => {})
    }
  }

  function toggle() {
    setLocale(locale.value === 'en' ? 'bn' : 'en')
  }

  /** After sign-in, adopt the user's saved preference unless they chose one on this device. */
  function syncFromUser(user) {
    const deviceChoice = local.get(LOCALE_STORAGE_KEY)
    if (!deviceChoice && user?.locale) {
      setLocale(user.locale, { persistRemote: false })
    } else if (deviceChoice && user && user.locale !== deviceChoice) {
      setLocale(deviceChoice)
    }
  }

  applyLocale(locale.value)

  return { locale, setLocale, toggle, syncFromUser }
})
