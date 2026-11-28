<script setup>
import { useI18n } from 'vue-i18n'
import { useFormat } from '@/composables/useFormat'

defineProps({
  tiers: { type: Array, default: () => [] }, // [{ min_quantity, max_quantity, unit_price }]
  activeQuantity: { type: Number, default: 0 },
  unit: { type: String, default: '' },
})

const { t } = useI18n()
const { formatCurrency, formatNumber } = useFormat()

function isActive(tier, qty) {
  return qty >= tier.min_quantity && (tier.max_quantity === null || qty <= tier.max_quantity)
}
</script>

<template>
  <table v-if="tiers.length" class="w-full text-xs">
    <caption class="sr-only">{{ t('catalog.tier_pricing') }}</caption>
    <tbody>
      <tr v-for="tier in tiers" :key="tier.min_quantity" class="border-b border-line last:border-0" :class="isActive(tier, activeQuantity) ? 'bg-brand-50 font-semibold text-brand-800' : 'text-slate-600'">
        <td class="py-1.5 pl-2 tabular">
          {{ tier.max_quantity === null ? t('catalog.tier_open', { min: formatNumber(tier.min_quantity) }) : t('catalog.tier_range', { min: formatNumber(tier.min_quantity), max: formatNumber(tier.max_quantity) }) }}
          <span class="text-slate-400">{{ unit }}</span>
        </td>
        <td class="py-1.5 pr-2 text-right tabular">{{ formatCurrency(tier.unit_price) }}</td>
      </tr>
    </tbody>
  </table>
</template>
