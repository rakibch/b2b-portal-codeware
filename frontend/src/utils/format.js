/**
 * Locale-aware formatting for BDT amounts, numbers and dates.
 * bn → Bangla numerals; both locales use South-Asian (lakh/crore) grouping.
 */
const NUMBER_LOCALE = { en: 'en-IN', bn: 'bn-BD' }
const DATE_LOCALE = { en: 'en-GB', bn: 'bn-BD' }

const cache = new Map()

function numberFormatter(locale, minimumFractionDigits, maximumFractionDigits) {
  const key = `${locale}|${minimumFractionDigits}|${maximumFractionDigits}`
  if (!cache.has(key)) {
    cache.set(
      key,
      new Intl.NumberFormat(NUMBER_LOCALE[locale] ?? 'en-IN', { minimumFractionDigits, maximumFractionDigits }),
    )
  }
  return cache.get(key)
}

function toNumber(value) {
  if (value === null || value === undefined || value === '') return 0
  const n = typeof value === 'number' ? value : Number.parseFloat(String(value))
  return Number.isFinite(n) ? n : 0
}

export function formatNumber(value, locale = 'en', decimals = 0) {
  return numberFormatter(locale, decimals, decimals).format(toNumber(value))
}

/** formatCurrency('1234567.5', 'bn') → "৳ ১২,৩৪,৫৬৭.৫০" */
export function formatCurrency(amount, locale = 'en', { decimals = 2, symbol = '৳' } = {}) {
  const n = toNumber(amount)
  const formatted = numberFormatter(locale, decimals, decimals).format(Math.abs(n))
  return `${n < 0 ? '-' : ''}${symbol} ${formatted}`
}

export function formatDate(value, locale = 'en', options = { day: '2-digit', month: 'short', year: 'numeric' }) {
  if (!value) return '—'
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return '—'
  return new Intl.DateTimeFormat(DATE_LOCALE[locale] ?? 'en-GB', options).format(date)
}

export function formatDateTime(value, locale = 'en') {
  // Bangla UI uses 24-hour time (avoids mixing Latin AM/PM into Bangla text).
  return formatDate(value, locale, { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: locale === 'bn' ? 'h23' : 'h12' })
}

const RELATIVE_UNITS = [
  ['year', 31536000],
  ['month', 2592000],
  ['day', 86400],
  ['hour', 3600],
  ['minute', 60],
]

export function formatRelative(value, locale = 'en') {
  if (!value) return ''
  const seconds = Math.round((new Date(value).getTime() - Date.now()) / 1000)
  const rtf = new Intl.RelativeTimeFormat(locale === 'bn' ? 'bn' : 'en', { numeric: 'auto' })
  for (const [unit, size] of RELATIVE_UNITS) {
    if (Math.abs(seconds) >= size) return rtf.format(Math.round(seconds / size), unit)
  }
  return rtf.format(seconds, 'second')
}
