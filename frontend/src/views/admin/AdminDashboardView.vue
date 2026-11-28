<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import KpiTile from '@/components/admin/KpiTile.vue'
import SalesChart from '@/components/admin/SalesChart.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'

const { t, locale } = useI18n()
const { formatCurrency, formatNumber, formatDate } = useFormat()
const auth = useAuthStore()
const data = ref(null)

const revenueDelta = computed(() => {
  if (!data.value?.revenue) return null
  const prev = Number(data.value.revenue.last_month)
  const cur = Number(data.value.revenue.this_month)
  if (!prev) return null
  return Math.round(((cur - prev) / prev) * 100)
})

async function load() {
  data.value = await adminApi.dashboard()
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <div>
    <PageHeader :title="t('dashboard.welcome', { name: auth.user?.name?.split(' ')[0] ?? '' })" :subtitle="t('app.admin_portal')">
      <template #actions><BaseButton variant="secondary" icon="Package" :to="{ name: 'admin.orders' }">{{ t('nav.orders') }}</BaseButton></template>
    </PageHeader>

    <SpinnerBlock v-if="!data" />
    <template v-else>
      <div v-if="data.overdue?.count" class="mb-6 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
        <AppIcon name="AlertTriangle" :size="18" />
        <span class="flex-1">{{ t('dashboard.overdue_alert', { count: formatNumber(data.overdue.count), amount: formatCurrency(data.overdue.amount) }) }}</span>
        <BaseButton size="sm" variant="secondary" :to="{ name: 'admin.receivables', query: { status: 'overdue' } }">{{ t('common.view') }}</BaseButton>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiTile v-if="data.revenue" :label="t('dashboard.total_revenue')" :value="formatCurrency(data.revenue.this_month)" icon="Landmark"
          :hint="revenueDelta === null ? null : t('dashboard.vs_last_month', { delta: `${revenueDelta >= 0 ? '+' : ''}${formatNumber(revenueDelta)}%` })" />
        <KpiTile :label="t('dashboard.pending_orders')" :value="formatNumber(data.operations.pending_orders)" icon="Package" :to="{ name: 'admin.orders', query: { status: 'pending' } }" />
        <KpiTile v-if="data.kyc_pending !== undefined" :label="t('dashboard.pending_kyc')" :value="formatNumber(data.kyc_pending)" icon="ShieldCheck" :tone="data.kyc_pending ? 'warning' : 'default'" :to="{ name: 'admin.kyc' }" />
        <KpiTile v-if="data.receivables" :label="t('dashboard.receivables')" :value="formatCurrency(data.receivables)" icon="Wallet" :to="{ name: 'admin.receivables' }" />
        <KpiTile :label="t('dashboard.to_dispatch')" :value="formatNumber(data.operations.to_dispatch)" icon="Truck" :to="{ name: 'admin.fulfilment' }" />
        <KpiTile v-if="data.payments_to_verify !== undefined" :label="t('dashboard.pending_payments')" :value="formatNumber(data.payments_to_verify)" icon="Wallet" :tone="data.payments_to_verify ? 'warning' : 'default'" :to="{ name: 'admin.payments' }" />
        <KpiTile :label="t('dashboard.low_stock')" :value="formatNumber(data.low_stock_count)" icon="Boxes" :tone="data.low_stock_count ? 'danger' : 'default'" :to="{ name: 'admin.products', query: { low_stock: '1' } }" />
        <KpiTile v-if="data.awaiting_buyer_approval !== undefined" :label="t('dashboard.awaiting_buyer_approval')" :value="formatNumber(data.awaiting_buyer_approval)" icon="Users" />
      </div>

      <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <section v-if="data.sales_chart" class="card p-5 xl:col-span-2">
          <SalesChart :series="data.sales_chart" :title="t('dashboard.sales_chart')" />
        </section>

        <section class="card">
          <h2 class="border-b border-line px-5 py-3 text-sm font-semibold">{{ t('dashboard.low_stock') }}</h2>
          <EmptyState v-if="!data.low_stock.length" icon="Boxes" :title="t('dashboard.no_low_stock')" />
          <ul v-else class="divide-y divide-line">
            <li v-for="p in data.low_stock" :key="p.id">
              <RouterLink :to="{ name: 'admin.product-edit', params: { id: p.id } }" class="flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50">
                <div class="min-w-0 flex-1"><p class="truncate text-sm">{{ p.name }}</p><p class="font-mono text-xs text-muted">{{ p.sku }}</p></div>
                <p class="text-right text-sm tabular" :class="p.stock_quantity === 0 ? 'font-semibold text-red-600' : 'text-amber-700'">{{ formatNumber(p.stock_quantity) }} {{ p.unit }}</p>
              </RouterLink>
            </li>
          </ul>
        </section>

        <section v-if="data.recent_orders" class="card xl:col-span-3">
          <header class="flex items-center justify-between border-b border-line px-5 py-3">
            <h2 class="text-sm font-semibold">{{ t('dashboard.recent_orders') }}</h2>
            <RouterLink :to="{ name: 'admin.orders' }" class="text-xs font-medium text-brand-700 hover:underline">{{ t('common.view_all') }}</RouterLink>
          </header>
          <ul class="divide-y divide-line">
            <li v-for="o in data.recent_orders" :key="o.order_number">
              <RouterLink :to="{ name: 'admin.order', params: { number: o.order_number } }" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
                <span class="font-mono text-sm font-medium">{{ o.order_number }}</span>
                <span class="min-w-0 flex-1 truncate text-sm text-muted">{{ o.company }}</span>
                <span class="text-xs text-muted">{{ formatDate(o.created_at) }}</span>
                <StatusBadge :status="o.status" />
                <span class="w-28 text-right text-sm font-semibold tabular">{{ formatCurrency(o.grand_total) }}</span>
              </RouterLink>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>
