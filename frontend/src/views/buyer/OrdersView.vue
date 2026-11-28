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
import { buyerApi } from '@/api/buyer'
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
  { key: 'created_at', label: t('orders.placed_on') },
  { key: 'placed_by', label: t('orders.placed_by'), class: 'hidden md:table-cell' },
  { key: 'status', label: t('common.status') },
  { key: 'payment', label: t('orders.payment'), class: 'hidden lg:table-cell' },
  { key: 'total', label: t('common.total'), align: 'right' },
])

async function load() {
  result.value = null
  result.value = await buyerApi.orders({ ...route.query, per_page: 20 })
}

function setQuery(patch) {
  router.replace({ query: Object.fromEntries(Object.entries({ ...route.query, page: undefined, ...patch }).filter(([, v]) => v !== undefined && v !== '' && v !== null)) })
}

const onSearch = debounce((q) => setQuery({ q }), 350)
watch(search, onSearch)
watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('orders.title')" />
    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
      <input v-model="search" type="search" class="input sm:max-w-xs" :placeholder="t('common.search_placeholder')" :aria-label="t('common.search')" />
      <select class="input sm:w-56" :value="route.query.status ?? ''" :aria-label="t('common.status')" @change="setQuery({ status: $event.target.value })">
        <option value="">{{ t('common.all') }}</option>
        <option v-for="s in statuses" :key="s" :value="s">{{ t(`order_status.${s}`) }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" :checked="route.query.approval === 'pending'" @change="setQuery({ approval: $event.target.checked ? 'pending' : undefined })" />
        {{ t('orders.awaiting_approval') }}
      </label>
    </div>

    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="Package" :title="t('orders.no_orders')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" row-key="order_number" clickable @row-click="router.push({ name: 'buyer.order', params: { number: $event.order_number } })">
          <template #cell-order_number="{ row }"><span class="font-mono font-medium">{{ row.order_number }}</span><span v-if="row.po_number" class="block text-xs text-muted">PO: {{ row.po_number }}</span></template>
          <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}<span class="block text-xs text-muted">{{ t('orders.items_count', { count: formatNumber(row.items_count) }) }}</span></template>
          <template #cell-placed_by="{ row }">{{ row.placed_by?.name }}</template>
          <template #cell-status="{ row }">
            <div class="flex flex-col items-start gap-1">
              <StatusBadge :status="row.status" />
              <StatusBadge v-if="row.approval_status.value === 'pending'" :status="row.approval_status" />
            </div>
          </template>
          <template #cell-payment="{ row }"><span class="text-xs text-muted">{{ row.payment_method.label }}</span><StatusBadge class="mt-1" :status="row.payment_status" /></template>
          <template #cell-total="{ row }"><span class="font-semibold">{{ formatCurrency(row.totals.grand_total) }}</span></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="setQuery({ page: $event })" />
      </template>
    </div>
  </div>
</template>
