<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DataTable from '@/components/shared/DataTable.vue'
import PaginationBar from '@/components/shared/PaginationBar.vue'
import { adminApi } from '@/api/admin'
import { debounce } from '@/composables/useDebounce'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'

const { t, locale } = useI18n()
const { formatCurrency, formatNumber } = useFormat()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const search = ref(String(route.query.q ?? ''))

const columns = computed(() => [
  { key: 'name', label: t('common.product') },
  { key: 'category', label: t('catalog.category'), class: 'hidden lg:table-cell' },
  { key: 'price', label: t('catalog.list_price'), align: 'right' },
  { key: 'tiers', label: t('admin.tiers'), align: 'right', class: 'hidden md:table-cell' },
  { key: 'stock', label: t('admin.stock'), align: 'right' },
  { key: 'status', label: t('common.status'), class: 'hidden sm:table-cell' },
])

function setQuery(patch) {
  router.replace({ query: Object.fromEntries(Object.entries({ ...route.query, page: undefined, ...patch }).filter(([, v]) => v !== undefined && v !== '')) })
}

async function load() {
  result.value = null
  result.value = await adminApi.products({ ...route.query, low_stock: route.query.low_stock === '1' ? 1 : undefined })
}

const onSearch = debounce((q) => setQuery({ q }), 350)
watch(search, onSearch)
watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('nav.products')">
      <template v-if="auth.can('catalog.manage')" #actions><BaseButton icon="Plus" :to="{ name: 'admin.product-new' }">{{ t('admin.new_product') }}</BaseButton></template>
    </PageHeader>
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
      <input v-model="search" type="search" class="input sm:max-w-xs" :placeholder="t('catalog.search_placeholder')" :aria-label="t('common.search')" />
      <select class="input sm:w-44" :value="route.query.status ?? ''" :aria-label="t('common.status')" @change="setQuery({ status: $event.target.value })">
        <option value="">{{ t('common.all') }}</option>
        <option value="active">{{ t('status.active') }}</option>
        <option value="inactive">{{ t('status.inactive') }}</option>
        <option value="trashed">{{ t('admin.deleted') }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" :checked="route.query.low_stock === '1'" @change="setQuery({ low_stock: $event.target.checked ? '1' : undefined })" />{{ t('dashboard.low_stock') }}</label>
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="Boxes" :title="t('common.no_results')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" clickable @row-click="router.push({ name: 'admin.product-edit', params: { id: $event.id } })">
          <template #cell-name="{ row }"><p class="font-medium">{{ row.name }}</p><p class="font-mono text-xs text-muted">{{ row.sku }}<span v-if="row.brand"> · {{ row.brand.name }}</span></p></template>
          <template #cell-category="{ row }">{{ row.category?.name }}</template>
          <template #cell-price="{ row }">{{ formatCurrency(row.list_price) }}<span class="block text-xs text-muted">MOQ {{ formatNumber(row.moq) }}</span></template>
          <template #cell-tiers="{ row }">{{ formatNumber(row.tiers?.length ?? 0) }}</template>
          <template #cell-stock="{ row }"><span :class="row.stock_status === 'out_of_stock' ? 'font-semibold text-red-600' : row.stock_status === 'low_stock' ? 'text-amber-700' : ''">{{ formatNumber(row.available_quantity) }}</span> <span class="text-xs text-muted">{{ row.unit?.short_name }}</span></template>
          <template #cell-status="{ row }"><StatusBadge :status="row.is_active ? 'active' : 'inactive'" :label="row.is_active ? t('status.active') : t('status.inactive')" /></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="setQuery({ page: $event })" />
      </template>
    </div>
  </div>
</template>
