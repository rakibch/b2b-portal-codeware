<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import PaginationBar from '@/components/shared/PaginationBar.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useToastStore } from '@/stores/toast'

const { t, locale } = useI18n()
const { formatCurrency, formatDate, formatNumber } = useFormat()
const route = useRoute()
const router = useRouter()
const toast = useToastStore()
const view = computed(() => String(route.query.view ?? 'queue'))
const orders = ref(null)
const shipments = ref(null)
const busy = ref(null)

async function load() {
  if (view.value === 'queue') {
    orders.value = null
    orders.value = await adminApi.orders({ 'status[]': ['confirmed', 'processing', 'partially_dispatched'], page: route.query.page || undefined })
  } else {
    shipments.value = null
    shipments.value = await adminApi.shipments({ status: view.value, page: route.query.page || undefined })
  }
}

async function act(key, fn) {
  busy.value = key
  try {
    const result = await fn()
    toast.success(result.message)
    await load()
  } catch (e) {
    toast.error(e.message)
  } finally {
    busy.value = null
  }
}

watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('admin.fulfilment_queue')" />
    <div class="mb-4 flex flex-wrap gap-2" role="tablist">
      <button v-for="v in ['queue', 'packing', 'dispatched', 'delivered']" :key="v" type="button" role="tab" :aria-selected="view === v" class="rounded-full px-3 py-1 text-sm" :class="view === v ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-line hover:bg-slate-50'" @click="router.replace({ query: { view: v } })">
        {{ v === 'queue' ? t('dashboard.to_dispatch') : t(`shipment_status.${v}`) }}
      </button>
    </div>

    <div class="card">
      <template v-if="view === 'queue'">
        <SpinnerBlock v-if="!orders" />
        <EmptyState v-else-if="!orders.data.length" icon="Truck" :title="t('common.empty')" />
        <ul v-else class="divide-y divide-line">
          <li v-for="o in orders.data" :key="o.order_number">
            <RouterLink :to="{ name: 'admin.order', params: { number: o.order_number } }" class="flex flex-wrap items-center gap-3 px-5 py-3 hover:bg-slate-50">
              <span class="font-mono text-sm font-medium">{{ o.order_number }}</span>
              <span class="min-w-0 flex-1 truncate text-sm">{{ o.company?.name }}</span>
              <span class="text-xs text-muted">{{ formatDate(o.confirmed_at ?? o.created_at) }} · {{ t('orders.items_count', { count: formatNumber(o.items_count) }) }}</span>
              <StatusBadge :status="o.status" />
              <span class="w-28 text-right text-sm tabular">{{ formatCurrency(o.totals.grand_total) }}</span>
            </RouterLink>
          </li>
        </ul>
        <PaginationBar v-if="orders" :meta="orders.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
      </template>
      <template v-else>
        <SpinnerBlock v-if="!shipments" />
        <EmptyState v-else-if="!shipments.data.length" icon="Truck" :title="t('common.empty')" />
        <ul v-else class="divide-y divide-line">
          <li v-for="s in shipments.data" :key="s.challan_number" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
            <span class="font-mono font-medium">{{ s.challan_number }}</span>
            <RouterLink :to="{ name: 'admin.order', params: { number: s.order_number } }" class="font-mono text-xs text-brand-700 hover:underline">{{ s.order_number }}</RouterLink>
            <span class="min-w-0 flex-1 truncate text-xs text-muted">{{ s.items.map((l) => `${l.sku} × ${formatNumber(l.quantity)}`).join(', ') }}</span>
            <StatusBadge :status="s.status" />
            <BaseButton size="sm" variant="ghost" icon="FileText" @click="adminApi.challanPdf(s.challan_number, locale)">{{ t('orders.challan') }}</BaseButton>
            <BaseButton v-if="s.status.value === 'packing'" size="sm" icon="Truck" :loading="busy === s.challan_number" @click="act(s.challan_number, () => adminApi.dispatchShipment(s.challan_number))">{{ t('orders.dispatch') }}</BaseButton>
            <BaseButton v-if="s.status.value === 'dispatched'" size="sm" variant="secondary" :loading="busy === s.challan_number" @click="act(s.challan_number, () => adminApi.deliverShipment(s.challan_number))">{{ t('orders.mark_delivered') }}</BaseButton>
          </li>
        </ul>
        <PaginationBar v-if="shipments" :meta="shipments.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
      </template>
    </div>
  </div>
</template>
