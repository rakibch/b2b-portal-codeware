<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '@/composables/useFormat'

const props = defineProps({ credit: { type: Object, required: true } })
const { t } = useI18n()
const { formatCurrency, formatNumber } = useFormat()

const percent = computed(() => {
  const limit = Number(props.credit.limit)
  if (!limit) return 0
  return Math.min(100, Math.round((Number(props.credit.used) / limit) * 100))
})
const tone = computed(() => (percent.value >= 90 ? 'bg-red-500' : percent.value >= 70 ? 'bg-amber-500' : 'bg-brand-600'))
</script>

<template>
  <div>
    <div class="flex items-baseline justify-between gap-2">
      <p class="text-sm font-medium text-slate-600">{{ t('credit.limit') }}</p>
      <p class="text-sm tabular text-muted">{{ formatNumber(percent) }}%</p>
    </div>
    <p v-if="!credit.enabled" class="mt-2 text-sm text-muted">{{ t('credit.not_enabled') }}</p>
    <template v-else>
      <div class="mt-3 h-2.5 w-full overflow-hidden rounded-full bg-slate-100" role="progressbar" :aria-valuenow="percent" aria-valuemin="0" aria-valuemax="100" :aria-label="t('credit.used')">
        <div class="h-full rounded-full transition-all" :class="tone" :style="{ width: `${percent}%` }" />
      </div>
      <dl class="mt-4 grid grid-cols-3 gap-2 text-xs">
        <div><dt class="text-muted">{{ t('credit.used') }}</dt><dd class="mt-0.5 font-semibold tabular text-ink">{{ formatCurrency(credit.used) }}</dd></div>
        <div><dt class="text-muted">{{ t('credit.available') }}</dt><dd class="mt-0.5 font-semibold tabular text-emerald-700">{{ formatCurrency(credit.available) }}</dd></div>
        <div><dt class="text-muted">{{ t('credit.limit') }}</dt><dd class="mt-0.5 font-semibold tabular text-ink">{{ formatCurrency(credit.limit) }}</dd></div>
      </dl>
      <p class="mt-3 text-xs text-muted">
        {{ t('credit.terms') }}: {{ t('credit.net_days', { days: formatNumber(credit.payment_terms_days) }) }}
        <span v-if="credit.on_hold" class="ml-2 font-semibold text-red-600">· {{ t('credit.on_hold') }}</span>
      </p>
    </template>
  </div>
</template>
