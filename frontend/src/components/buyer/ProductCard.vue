<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import QuantityStepper from '@/components/shared/QuantityStepper.vue'
import TierPriceTable from '@/components/shared/TierPriceTable.vue'
import { useFormat } from '@/composables/useFormat'
import { useLiveQuote } from '@/composables/useLiveQuote'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const props = defineProps({
  product: { type: Object, required: true },
  layout: { type: String, default: 'grid' }, // grid | list
  canBuy: { type: Boolean, default: false },
})

const { t } = useI18n()
const { formatCurrency, formatNumber } = useFormat()
const cart = useCartStore()
const toast = useToastStore()

const quantity = ref(props.product.moq)
const adding = ref(false)
const productId = computed(() => (props.canBuy ? props.product.id : null))
const { quote, loading } = useLiveQuote(productId, quantity, { initial: props.product.pricing?.quote ?? null })

watch(() => props.product.id, () => {
  quantity.value = props.product.moq
})

const stockLabel = computed(() => t(`status.${props.product.stock_status}`))
const outOfStock = computed(() => props.product.stock_status === 'out_of_stock')
const errorMessage = computed(() => quote.value?.errors?.[0]?.message ?? null)

async function addToCart() {
  adding.value = true
  try {
    await cart.add([{ product_id: props.product.id, quantity: quantity.value }], 'catalog')
    toast.success(t('catalog.added_to_cart', { name: props.product.name }))
  } catch (e) {
    toast.error(e?.context?.lines?.[0]?.errors?.[0]?.message ?? e.message)
  } finally {
    adding.value = false
  }
}
</script>

<template>
  <article class="card flex overflow-hidden" :class="layout === 'grid' ? 'flex-col' : 'flex-col sm:flex-row'">
    <RouterLink :to="{ name: 'buyer.product', params: { slug: product.slug } }" class="block shrink-0 bg-slate-50" :class="layout === 'grid' ? 'aspect-[4/3]' : 'h-40 sm:h-auto sm:w-44'">
      <img v-if="product.image_url" :src="product.image_url" :alt="product.name" loading="lazy" class="h-full w-full object-cover" />
      <div v-else class="flex h-full w-full items-center justify-center text-slate-300"><AppIcon name="Package" :size="40" /></div>
    </RouterLink>

    <div class="flex min-w-0 flex-1 flex-col gap-3 p-4" :class="layout === 'list' ? 'sm:flex-row sm:items-start sm:gap-6' : ''">
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 text-[11px] text-muted">
          <span class="font-mono">{{ product.sku }}</span>
          <span v-if="product.brand">· {{ product.brand.name }}</span>
        </div>
        <RouterLink :to="{ name: 'buyer.product', params: { slug: product.slug } }" class="mt-1 line-clamp-2 block font-semibold text-ink hover:text-brand-700">{{ product.name }}</RouterLink>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <StatusBadge :status="product.stock_status" :label="stockLabel" />
          <span class="text-xs text-muted">{{ t('catalog.moq_full', { moq: formatNumber(product.moq), unit: product.unit?.short_name }) }}</span>
        </div>
        <p v-if="product.pack_label && product.pack_size > 1" class="mt-1 text-xs text-muted">
          {{ t('catalog.pack', { pack: product.pack_label, size: formatNumber(product.pack_size), unit: product.unit?.short_name }) }}
        </p>

        <TierPriceTable v-if="canBuy && layout === 'list'" class="mt-3 max-w-xs" :tiers="product.pricing?.tier_table ?? []" :active-quantity="quantity" :unit="product.unit?.short_name" />
      </div>

      <div class="flex flex-col gap-3" :class="layout === 'list' ? 'sm:w-64' : ''">
        <template v-if="canBuy">
          <div>
            <p class="text-[11px] text-muted">{{ t('catalog.your_price') }}</p>
            <p class="flex items-baseline gap-2">
              <span class="text-lg font-semibold tabular text-ink" :class="loading ? 'opacity-50' : ''">{{ formatCurrency(quote?.unit_price ?? product.list_price) }}</span>
              <span class="text-xs text-muted">{{ t('catalog.per_unit', { unit: product.unit?.short_name }) }}</span>
            </p>
            <p v-if="quote && quote.unit_price !== quote.list_price" class="text-xs text-muted line-through tabular">{{ formatCurrency(quote.list_price) }}</p>
            <p v-if="quote?.next_tier" class="mt-1 text-xs font-medium text-emerald-700">
              {{ t('catalog.next_tier_hint', { qty: formatNumber(quote.next_tier.additional_quantity), price: formatCurrency(quote.next_tier.unit_price) }) }}
            </p>
          </div>

          <TierPriceTable v-if="layout === 'grid'" :tiers="product.pricing?.tier_table ?? []" :active-quantity="quantity" :unit="product.unit?.short_name" />

          <div class="flex flex-wrap items-center gap-2">
            <QuantityStepper v-model="quantity" :product="product" size="sm" :disabled="outOfStock" />
            <BaseButton size="sm" icon="ShoppingCart" class="min-w-[7.5rem] flex-1 whitespace-nowrap" :loading="adding" :disabled="outOfStock || Boolean(errorMessage)" @click="addToCart">{{ t('catalog.add_to_cart') }}</BaseButton>
          </div>
          <p v-if="errorMessage" class="text-xs text-red-600" role="alert">{{ errorMessage }}</p>
          <p v-else-if="quote" class="text-xs text-muted tabular">{{ t('catalog.line_total') }}: {{ formatCurrency(quote.line_total) }} <span class="text-slate-400">({{ t('catalog.vat_included', { percent: formatNumber(quote.vat_percent, 2) }) }})</span></p>
        </template>
        <p v-else class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-muted">
          <AppIcon name="ShieldCheck" :size="14" /> {{ t('kyc.prices_locked') }}
        </p>
      </div>
    </div>
  </article>
</template>
