<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import QuantityStepper from '@/components/shared/QuantityStepper.vue'
import TierPriceTable from '@/components/shared/TierPriceTable.vue'
import { catalogApi } from '@/api/catalog'
import { useFormat } from '@/composables/useFormat'
import { useLiveQuote } from '@/composables/useLiveQuote'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ slug: { type: String, required: true } })

const { t, locale } = useI18n()
const { formatCurrency, formatNumber } = useFormat()
const auth = useAuthStore()
const cart = useCartStore()
const toast = useToastStore()
const router = useRouter()

const product = ref(null)
const loading = ref(true)
const notFound = ref(false)
const quantity = ref(1)
const adding = ref(false)
const productId = computed(() => (auth.canTransact && product.value ? product.value.id : null))
const { quote, loading: quoting } = useLiveQuote(productId, quantity)

async function load() {
  loading.value = true
  notFound.value = false
  try {
    product.value = await catalogApi.product(props.slug)
    quantity.value = product.value.moq
    quote.value = product.value.pricing?.quote ?? null
  } catch (e) {
    if (e.status === 404) notFound.value = true
  } finally {
    loading.value = false
  }
}

watch(() => [props.slug, locale.value], load, { immediate: true })

const errorMessage = computed(() => quote.value?.errors?.[0]?.message ?? null)

async function addToCart() {
  adding.value = true
  try {
    await cart.add([{ product_id: product.value.id, quantity: quantity.value }])
    toast.success(t('catalog.added_to_cart', { name: product.value.name }))
  } catch (e) {
    toast.error(e?.context?.lines?.[0]?.errors?.[0]?.message ?? e.message)
  } finally {
    adding.value = false
  }
}

function requestQuote() {
  router.push({ name: 'buyer.rfq-new', query: { product: product.value.id, sku: product.value.sku, qty: quantity.value } })
}
</script>

<template>
  <SpinnerBlock v-if="loading" />
  <div v-else-if="notFound" class="card"><EmptyState icon="Search" :title="t('errors.not_found_title')" :description="t('errors.not_found')"><BaseButton :to="{ name: 'buyer.catalog' }">{{ t('nav.catalog') }}</BaseButton></EmptyState></div>
  <div v-else-if="product">
    <RouterLink :to="{ name: 'buyer.catalog' }" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink"><AppIcon name="ChevronLeft" :size="16" />{{ t('nav.catalog') }}</RouterLink>

    <div class="grid gap-6 lg:grid-cols-5">
      <div class="card overflow-hidden lg:col-span-2">
        <div class="aspect-square bg-slate-50">
          <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
          <div v-else class="flex h-full items-center justify-center text-slate-300"><AppIcon name="Package" :size="72" /></div>
        </div>
      </div>

      <div class="space-y-5 lg:col-span-3">
        <div>
          <p class="text-xs text-muted"><span class="font-mono">{{ product.sku }}</span><span v-if="product.brand"> · {{ product.brand.name }}</span><span v-if="product.category"> · {{ product.category.name }}</span></p>
          <h1 class="mt-1 text-2xl font-semibold text-ink">{{ product.name }}</h1>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <StatusBadge :status="product.stock_status" :label="t(`status.${product.stock_status}`)" />
            <span v-if="auth.canTransact" class="text-xs text-muted">{{ t('catalog.available', { qty: formatNumber(product.available_quantity) }) }} {{ product.unit?.short_name }}</span>
          </div>
        </div>

        <div v-if="auth.canTransact" class="card space-y-4 p-5">
          <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
              <p class="text-xs text-muted">{{ t('catalog.your_price') }}</p>
              <p class="text-3xl font-semibold tabular" :class="quoting ? 'opacity-50' : ''">{{ formatCurrency(quote?.unit_price ?? product.list_price) }}</p>
              <p class="text-xs text-muted">{{ t('catalog.per_unit', { unit: product.unit?.short_name }) }} · {{ t('catalog.vat_included', { percent: formatNumber(product.vat_percent, 2) }) }}</p>
              <p v-if="quote?.price_source_label" class="mt-1 text-xs font-medium text-brand-700">{{ quote.price_source_label }}</p>
            </div>
            <div class="text-right">
              <p class="text-xs text-muted">{{ t('catalog.line_total') }}</p>
              <p class="text-xl font-semibold tabular">{{ formatCurrency(quote?.line_total ?? 0) }}</p>
            </div>
          </div>
          <p v-if="quote?.next_tier" class="rounded-lg bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800">
            {{ t('catalog.next_tier_hint', { qty: formatNumber(quote.next_tier.additional_quantity), price: formatCurrency(quote.next_tier.unit_price) }) }}
          </p>
          <div class="flex flex-wrap items-center gap-3">
            <QuantityStepper v-model="quantity" :product="product" :disabled="product.stock_status === 'out_of_stock'" />
            <BaseButton icon="ShoppingCart" :loading="adding" :disabled="Boolean(errorMessage)" @click="addToCart">{{ t('catalog.add_to_cart') }}</BaseButton>
            <BaseButton v-if="$router.hasRoute('buyer.rfq-new')" variant="secondary" icon="MessageSquareQuote" @click="requestQuote">{{ t('catalog.request_quote') }}</BaseButton>
          </div>
          <p v-if="errorMessage" class="text-sm text-red-600" role="alert">{{ errorMessage }}</p>
          <p class="text-xs text-muted">
            {{ t('catalog.moq_full', { moq: formatNumber(product.moq), unit: product.unit?.short_name }) }}, {{ t('catalog.step', { step: formatNumber(product.step_quantity) }) }}
          </p>
        </div>
        <p v-else class="card flex items-center gap-2 p-4 text-sm text-muted"><AppIcon name="ShieldCheck" :size="16" />{{ t('kyc.prices_locked') }}</p>

        <div v-if="auth.canTransact && product.pricing?.tier_table?.length" class="card p-5">
          <h2 class="mb-3 text-sm font-semibold">{{ t('catalog.tier_pricing') }}</h2>
          <TierPriceTable :tiers="product.pricing.tier_table" :active-quantity="quantity" :unit="product.unit?.short_name" />
        </div>

        <div class="card p-5">
          <h2 class="mb-3 text-sm font-semibold">{{ t('catalog.specifications') }}</h2>
          <dl class="grid grid-cols-2 gap-3 text-sm">
            <div v-if="product.barcode"><dt class="text-muted">{{ t('catalog.barcode') }}</dt><dd class="font-mono">{{ product.barcode }}</dd></div>
            <div><dt class="text-muted">{{ t('catalog.unit') }}</dt><dd>{{ product.unit?.name }}</dd></div>
            <div v-if="product.pack_size > 1"><dt class="text-muted">{{ t('catalog.pack_size') }}</dt><dd>{{ t('catalog.pack', { pack: product.pack_label, size: formatNumber(product.pack_size), unit: product.unit?.short_name }) }}</dd></div>
            <div><dt class="text-muted">{{ t('catalog.vat') }}</dt><dd>{{ formatNumber(product.vat_percent, 2) }}%</dd></div>
          </dl>
          <p v-if="product.description" class="mt-4 text-sm leading-relaxed whitespace-pre-line text-slate-600">{{ product.description }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
