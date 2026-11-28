<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DataTable from '@/components/shared/DataTable.vue'
import PaginationBar from '@/components/shared/PaginationBar.vue'
import { adminApi } from '@/api/admin'
import { debounce } from '@/composables/useDebounce'
import { useFormat } from '@/composables/useFormat'

const { t, locale } = useI18n()
const { formatCurrency, formatDate, formatNumber } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const search = ref(String(route.query.q ?? ''))
const statuses = ['pending', 'confirmed', 'processing', 'partially_dispatched', 'dispatched', 'delivered', 'cancelled']

const columns = computed(() => [
  { key: 'order_number', label: t('orders.order_number') },
  { key: 'company', label: t('common.company') },
  { key: 'created_at', label: t('orders.placed_on'), class: 'hidden md:table-cell' },
  { key: 'status', label: t('common.status') },
  { key: 'payment', label: t('orders.payment'), class: 'hidden lg:table-cell' },
  { key: 'total', label: t('common.total'), align: 'right' },
])

function setQuery(patch) {
  router.replace({ query: Object.fromEntries(Object.entries({ ...route.query, page: undefined, ...patch }).filter(([, v]) => v !== undefined && v !== '')) })
}

async function load() {
  result.value = null
  const q = route.query
  result.value = await adminApi.orders({
    'status[]': q.status ? [].concat(q.status) : undefined,
    payment_method: q.payment_method || undefined,
    q: q.q || undefined,
    from: q.from || undefined,
    to: q.to || undefined,
    page: q.page || undefined,
  })
}

const onSearch = debounce((q) => setQuery({ q }), 350)
watch(search, onSearch)
watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('nav.orders')" />
    <div class="mb-4 flex flex-col flex-wrap gap-3 sm:flex-row">
      <input v-model="search" type="search" class="input sm:max-w-xs" :placeholder="t('common.search_placeholder')" :aria-label="t('common.search')" />
      <select class="input sm:w-52" :value="route.query.status ?? ''" :aria-label="t('common.status')" @change="setQuery({ status: $event.target.value })">
        <option value="">{{ t('common.all') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ t(`order_status.${s}`) }}</option>
      </select>
      <select class="input sm:w-52" :value="route.query.payment_method ?? ''" :aria-label="t('checkout.payment_method')" @change="setQuery({ payment_method: $event.target.value })">
        <option value="">{{ t('checkout.payment_method') }}: {{ t('common.all') }}</option>
        <option value="credit">{{ t('checkout.credit_short') }}</option>
        <option value="cod">{{ t('checkout.cod') }}</option>
        <option value="bank_transfer">{{ t('checkout.bank_transfer') }}</option>
        <option value="gateway">{{ t('checkout.gateway') }}</option>
      </select>
      <input class="input sm:w-40" type="date" :value="route.query.from ?? ''" :aria-label="t('common.from')" @change="setQuery({ from: $event.target.value })" />
      <input class="input sm:w-40" type="date" :value="route.query.to ?? ''" :aria-label="t('common.to')" @change="setQuery({ to: $event.target.value })" />
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="Package" :title="t('orders.no_orders')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" row-key="order_number" clickable @row-click="router.push({ name: 'admin.order', params: { number: $event.order_number } })">
          <template #cell-order_number="{ row }"><span class="font-mono font-medium">{{ row.order_number }}</span><span class="block text-xs text-muted">{{ t('orders.items_count', { count: formatNumber(row.items_count) }) }}</span></template>
          <template #cell-company="{ row }">{{ row.company?.name }}<span class="block text-xs text-muted">{{ row.placed_by?.name }}</span></template>
          <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
          <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
          <template #cell-payment="{ row }"><span class="text-xs text-muted">{{ row.payment_method.label }}</span><StatusBadge class="mt-1" :status="row.payment_status" /></template>
          <template #cell-total="{ row }"><span class="font-semibold">{{ formatCurrency(row.totals.grand_total) }}</span></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="setQuery({ page: $event })" />
      </template>
    </div>
  </div>
</template>
