import { createI18n } from 'vue-i18n'
import en from './locales/en.json'
import bn from './locales/bn.json'
import { local } from '@/utils/storage'

export const SUPPORTED_LOCALES = ['en', 'bn']
export const LOCALE_STORAGE_KEY = 'b2b.locale'

export function detectInitialLocale() {
  const stored = local.get(LOCALE_STORAGE_KEY)
  if (SUPPORTED_LOCALES.includes(stored)) return stored
  const browser = (typeof navigator !== 'undefined' && navigator.language) || 'en'
  return browser.toLowerCase().startsWith('bn') ? 'bn' : 'en'
}

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: detectInitialLocale(),
  fallbackLocale: 'en',
  messages: { en, bn },
  missingWarn: import.meta.env.DEV,
  fallbackWarn: false,
})

/** Applies a locale to vue-i18n and the document (lang attribute drives Bangla typography). */
export function applyLocale(locale) {
  const next = SUPPORTED_LOCALES.includes(locale) ? locale : 'en'
  i18n.global.locale.value = next
  if (typeof document !== 'undefined') {
    document.documentElement.lang = next
    document.title = i18n.global.t('app.name')
  }
  return next
}
