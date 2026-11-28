<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import CreditMeter from '@/components/buyer/CreditMeter.vue'
import { buyerApi } from '@/api/buyer'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const { t, locale } = useI18n()
const { formatCurrency, formatNumber, formatDate } = useFormat()
const auth = useAuthStore()
const cart = useCartStore()
const toast = useToastStore()
const router = useRouter()
const data = ref(null)
const adding = ref(null)

async function load() {
  data.value = await buyerApi.dashboard()
}

async function quickAdd(item) {
  adding.value = item.product_id
  try {
    await cart.add([{ product_id: item.product_id, quantity: item.suggested_quantity }], 'reorder')
    toast.success(t('catalog.added_to_cart', { name: item.name }))
  } catch (e) {
    toast.error(e?.context?.lines?.[0]?.errors?.[0]?.message ?? e.message)
  } finally {
    adding.value = null
  }
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <div>
    <PageHeader :title="t('dashboard.welcome', { name: auth.user?.name?.split(' ')[0] ?? '' })" :subtitle="auth.company?.name">
      <template #actions>
        <BaseButton variant="secondary" icon="Zap" :to="{ name: 'buyer.quick-order' }">{{ t('nav.quick_order') }}</BaseButton>
        <BaseButton icon="Store" :to="{ name: 'buyer.catalog' }">{{ t('nav.catalog') }}</BaseButton>
      </template>
    </PageHeader>

    <SpinnerBlock v-if="!data" />
    <template v-else>
      <div v-if="data.stats.overdue_invoices" class="mb-6 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <AppIcon name="AlertTriangle" :size="18" />
        <span class="flex-1">{{ t('dashboard.overdue_alert', { count: formatNumber(data.stats.overdue_invoices), amount: formatCurrency(data.stats.overdue_amount) }) }}</span>
        <BaseButton size="sm" variant="secondary" :to="{ name: 'buyer.invoices', query: { status: 'overdue' } }">{{ t('common.view') }}</BaseButton>
      </div>
      <div v-if="data.stats.awaiting_my_approval" class="mb-6 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <AppIcon name="ShieldCheck" :size="18" />
        <span class="flex-1">{{ t('dashboard.awaiting_approval', { count: formatNumber(data.stats.awaiting_my_approval) }) }}</span>
        <BaseButton size="sm" variant="secondary" :to="{ name: 'buyer.orders', query: { approval: 'pending' } }">{{ t('common.view') }}</BaseButton>
      </div>

      <div class="grid gap-4 md:grid-cols-3">
        <section class="card p-5 md:col-span-2"><CreditMeter :credit="data.credit" /></section>
        <section class="card grid grid-cols-2 gap-4 p-5 md:grid-cols-1">
          <RouterLink :to="{ name: 'buyer.orders' }" class="block">
            <p class="text-xs text-muted">{{ t('dashboard.open_orders') }}</p>
            <p class="text-2xl font-semibold tabular">{{ formatNumber(data.stats.open_orders) }}</p>
          </RouterLink>
          <RouterLink :to="{ name: 'buyer.invoices', query: { status: 'outstanding' } }" class="block">
            <p class="text-xs text-muted">{{ t('dashboard.outstanding_invoices') }}</p>
            <p class="text-2xl font-semibold tabular">{{ formatCurrency(data.stats.outstanding_amount) }}</p>
          </RouterLink>
        </section>
      </div>

      <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <section class="card">
          <header class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold">{{ t('dashboard.recent_orders') }}</h2>
            <RouterLink :to="{ name: 'buyer.orders' }" class="text-xs font-medium text-brand-700 hover:underline">{{ t('common.view_all') }}</RouterLink>
          </header>
          <EmptyState v-if="!data.recent_orders.length" icon="Package" :title="t('orders.no_orders')" />
          <ul v-else class="divide-y divide-line">
            <li v-for="o in data.recent_orders" :key="o.order_number">
              <RouterLink :to="{ name: 'buyer.order', params: { number: o.order_number } }" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                <div class="min-w-0 flex-1">
                  <p class="font-mono text-sm font-medium">{{ o.order_number }}</p>
                  <p class="text-xs text-muted">{{ formatDate(o.created_at) }} · {{ t('orders.items_count', { count: formatNumber(o.items_count) }) }}</p>
                </div>
                <StatusBadge :status="o.status" />
                <p class="w-28 text-right text-sm font-semibold tabular">{{ formatCurrency(o.grand_total) }}</p>
              </RouterLink>
            </li>
          </ul>
        </section>

        <section class="card">
          <header class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold">{{ t('dashboard.quick_reorder') }}</h2>
          </header>
          <EmptyState v-if="!data.quick_reorder.length" icon="Zap" :title="t('dashboard.no_reorder_items')" />
          <ul v-else class="divide-y divide-line">
            <li v-for="item in data.quick_reorder" :key="item.product_id" class="flex items-center gap-3 px-5 py-3">
              <div class="min-w-0 flex-1">
                <RouterLink :to="{ name: 'buyer.product', params: { slug: item.slug } }" class="block truncate text-sm font-medium hover:text-brand-700">{{ item.name }}</RouterLink>
                <p class="text-xs text-muted"><span class="font-mono">{{ item.sku }}</span> · {{ formatNumber(item.suggested_quantity) }} {{ item.unit }}</p>
              </div>
              <BaseButton size="sm" variant="secondary" icon="Plus" :loading="adding === item.product_id" :disabled="item.stock_status === 'out_of_stock'" @click="quickAdd(item)">{{ t('catalog.add_to_cart') }}</BaseButton>
            </li>
          </ul>
        </section>

        <section class="card">
          <header class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold">{{ t('dashboard.outstanding_invoices') }}</h2>
            <RouterLink :to="{ name: 'buyer.invoices' }" class="text-xs font-medium text-brand-700 hover:underline">{{ t('common.view_all') }}</RouterLink>
          </header>
          <EmptyState v-if="!data.outstanding_invoices.length" icon="FileText" :title="t('invoices.no_invoices')" />
          <ul v-else class="divide-y divide-line">
            <li v-for="i in data.outstanding_invoices" :key="i.invoice_number" class="flex items-center gap-3 px-5 py-3">
              <div class="min-w-0 flex-1">
                <p class="font-mono text-sm font-medium">{{ i.invoice_number }}</p>
                <p class="text-xs" :class="i.is_overdue ? 'font-semibold text-red-600' : 'text-muted'">{{ t('invoices.due_date') }}: {{ formatDate(i.due_date) }}</p>
              </div>
              <StatusBadge :status="i.is_overdue ? 'overdue' : i.status.value" :label="i.is_overdue ? t('invoices.overdue') : i.status.label" />
              <p class="w-28 text-right text-sm font-semibold tabular">{{ formatCurrency(i.balance_due) }}</p>
            </li>
          </ul>
        </section>

        <section class="card">
          <header class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold">{{ t('dashboard.pending_rfqs') }}</h2>
            <RouterLink :to="{ name: 'buyer.rfqs' }" class="text-xs font-medium text-brand-700 hover:underline">{{ t('common.view_all') }}</RouterLink>
          </header>
          <EmptyState v-if="!data.open_rfqs.length" icon="MessageSquareQuote" :title="t('rfq.no_rfqs')">
            <BaseButton size="sm" variant="secondary" @click="router.push({ name: 'buyer.rfq-new' })">{{ t('rfq.new') }}</BaseButton>
          </EmptyState>
          <ul v-else class="divide-y divide-line">
            <li v-for="r in data.open_rfqs" :key="r.reference">
              <RouterLink :to="{ name: 'buyer.rfq', params: { reference: r.reference } }" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
                <div class="min-w-0 flex-1">
                  <p class="font-mono text-sm font-medium">{{ r.reference }}</p>
                  <p class="text-xs text-muted">{{ formatDate(r.created_at) }} · {{ t('orders.items_count', { count: formatNumber(r.items_count) }) }}</p>
                </div>
                <StatusBadge :status="r.status" />
              </RouterLink>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>
