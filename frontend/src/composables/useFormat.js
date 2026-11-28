import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatCurrency as fc, formatDate as fd, formatDateTime as fdt, formatNumber as fn, formatRelative as fr } from '@/utils/format'

/** Locale-bound formatters — re-render automatically when the language switches. */
export function useFormat() {
  const { locale } = useI18n()
  const current = computed(() => locale.value)

  return {
    formatCurrency: (amount, options) => fc(amount, current.value, options),
    formatNumber: (value, decimals = 0) => fn(value, current.value, decimals),
    formatDate: (value, options) => fd(value, current.value, options),
    formatDateTime: (value) => fdt(value, current.value),
    formatRelative: (value) => fr(value, current.value),
  }
}

/** Standalone helper requested by the spec: formatCurrency(amount) in the active locale. */
export function useCurrency() {
  const { formatCurrency } = useFormat()
  return { formatCurrency }
}
