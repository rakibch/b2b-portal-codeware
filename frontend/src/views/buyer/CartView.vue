<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import QuantityStepper from '@/components/shared/QuantityStepper.vue'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const { t } = useI18n()
const { formatCurrency, formatNumber } = useFormat()
const cart = useCartStore()
const toast = useToastStore()
const confirmClear = ref(false)
const clearing = ref(false)

async function changeQuantity(item, quantity) {
  if (quantity === item.quantity) return
  try {
    await cart.updateQuantity(item.id, quantity)
  } catch (e) {
    toast.error(e?.context?.lines?.[0]?.errors?.[0]?.message ?? e.message)
  }
}

async function remove(item) {
  await cart.remove(item.id)
  toast.info(t('cart.removed'))
}

async function clearCart() {
  clearing.value = true
  try {
    await cart.clear()
    confirmClear.value = false
  } finally {
    clearing.value = false
  }
}

onMounted(() => cart.fetch({ force: true }))
</script>

<template>
  <div>
    <PageHeader :title="t('cart.title')" :subtitle="cart.count ? t('cart.items', { count: formatNumber(cart.count) }) : null">
      <template v-if="!cart.isEmpty" #actions>
        <BaseButton variant="ghost" icon="Trash2" @click="confirmClear = true">{{ t('cart.clear_cart') }}</BaseButton>
      </template>
    </PageHeader>

    <SpinnerBlock v-if="cart.loading && !cart.loaded" />
    <div v-else-if="cart.isEmpty" class="card">
      <EmptyState icon="ShoppingCart" :title="t('cart.empty')" :description="t('cart.empty_cta')">
        <div class="flex gap-2">
          <BaseButton :to="{ name: 'buyer.catalog' }">{{ t('nav.catalog') }}</BaseButton>
          <BaseButton variant="secondary" :to="{ name: 'buyer.quick-order' }">{{ t('nav.quick_order') }}</BaseButton>
        </div>
      </EmptyState>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-3">
      <section class="card divide-y divide-line lg:col-span-2">
        <div v-if="cart.hasErrors" class="flex items-center gap-2 bg-amber-50 px-5 py-3 text-sm text-amber-900" role="alert">
          <AppIcon name="AlertTriangle" :size="16" /> {{ t('cart.has_errors') }}
        </div>
        <article v-for="item in cart.items" :key="item.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center" :class="cart.busyItemIds.has(item.id) ? 'opacity-60' : ''">
          <div class="min-w-0 flex-1">
            <p class="text-xs text-muted"><span class="font-mono">{{ item.product?.sku }}</span><span v-if="item.product?.brand"> · {{ item.product.brand }}</span></p>
            <RouterLink v-if="item.product" :to="{ name: 'buyer.product', params: { slug: item.product.slug } }" class="font-medium text-ink hover:text-brand-700">{{ item.product.name }}</RouterLink>
            <p v-if="!item.available" class="mt-1 text-xs font-semibold text-red-600">{{ t('cart.unavailable') }}</p>
            <template v-else-if="item.pricing">
              <p class="mt-1 text-xs text-muted tabular">
                {{ formatCurrency(item.pricing.unit_price) }} / {{ item.product.unit.short_name }}
                <span class="ml-1 text-brand-700">· {{ item.pricing.price_source_label }}</span>
                <span v-if="item.pricing.unit_price !== item.pricing.list_price" class="ml-1 line-through">{{ formatCurrency(item.pricing.list_price) }}</span>
              </p>
              <p v-if="item.pricing.next_tier" class="mt-0.5 text-xs font-medium text-emerald-700">
                {{ t('catalog.next_tier_hint', { qty: formatNumber(item.pricing.next_tier.additional_quantity), price: formatCurrency(item.pricing.next_tier.unit_price) }) }}
              </p>
              <p v-for="err in item.pricing.errors" :key="err.code" class="mt-0.5 text-xs text-red-600">{{ err.message }}</p>
            </template>
          </div>
          <div class="flex items-center gap-4">
            <QuantityStepper v-if="item.product && item.available" :model-value="item.quantity" :product="item.product" size="sm" :disabled="cart.busyItemIds.has(item.id)" @update:model-value="changeQuantity(item, $event)" />
            <p class="w-28 text-right font-semibold tabular">{{ formatCurrency(item.pricing?.line_subtotal ?? 0) }}</p>
            <button type="button" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600" :aria-label="t('common.remove')" @click="remove(item)"><AppIcon name="Trash2" :size="16" /></button>
          </div>
        </article>
      </section>

      <aside class="card h-fit p-5">
        <h2 class="text-sm font-semibold">{{ t('checkout.order_summary') }}</h2>
        <dl class="mt-4 space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-muted">{{ t('cart.subtotal') }}</dt><dd class="tabular">{{ formatCurrency(cart.totals.subtotal) }}</dd></div>
          <div v-if="Number(cart.totals.discount_total) > 0" class="flex justify-between text-emerald-700"><dt>{{ t('cart.discount') }}</dt><dd class="tabular">− {{ formatCurrency(cart.totals.discount_total) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">{{ t('cart.vat') }}</dt><dd class="tabular">{{ formatCurrency(cart.totals.vat_total) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">{{ t('cart.shipping') }}</dt><dd class="text-xs text-muted">{{ t('cart.shipping_calculated') }}</dd></div>
          <div class="flex justify-between border-t border-line pt-3 text-base font-semibold"><dt>{{ t('cart.grand_total') }}</dt><dd class="tabular">{{ formatCurrency(cart.totals.grand_total) }}</dd></div>
        </dl>
        <BaseButton class="mt-5" block size="lg" :to="{ name: 'buyer.checkout' }" :disabled="cart.hasErrors">{{ t('cart.checkout') }}</BaseButton>
      </aside>
    </div>

    <ConfirmDialog v-model:open="confirmClear" :title="t('cart.clear_cart')" :message="t('cart.clear_confirm')" danger :loading="clearing" @confirm="clearCart" />
  </div>
</template>
