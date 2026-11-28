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
const { formatCurrency } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const search = ref(String(route.query.q ?? ''))

const columns = computed(() => [
  { key: 'name', label: t('common.company') },
  { key: 'group', label: t('kyc.customer_group'), class: 'hidden md:table-cell' },
  { key: 'manager', label: t('kyc.assign_manager'), class: 'hidden lg:table-cell' },
  { key: 'status', label: t('common.status') },
  { key: 'limit', label: t('credit.limit'), align: 'right', class: 'hidden sm:table-cell' },
  { key: 'outstanding', label: t('credit.outstanding'), align: 'right' },
])

function setQuery(patch) {
  router.replace({ query: Object.fromEntries(Object.entries({ ...route.query, page: undefined, ...patch }).filter(([, v]) => v !== undefined && v !== '')) })
}

async function load() {
  result.value = null
  result.value = await adminApi.companies({ ...route.query })
}

const onSearch = debounce((q) => setQuery({ q }), 350)
watch(search, onSearch)
watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('nav.companies')" />
    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
      <input v-model="search" type="search" class="input sm:max-w-xs" :placeholder="t('common.search_placeholder')" :aria-label="t('common.search')" />
      <select class="input sm:w-48" :value="route.query.status ?? ''" :aria-label="t('common.status')" @change="setQuery({ status: $event.target.value })">
        <option value="">{{ t('common.all') }}</option>
        <option v-for="s in ['approved', 'pending', 'rejected', 'suspended']" :key="s" :value="s">{{ t(`kyc_status.${s}`) }}</option>
      </select>
      <select class="input sm:w-48" :value="route.query.sort ?? 'name'" :aria-label="t('catalog.sort')" @change="setQuery({ sort: $event.target.value })">
        <option value="name">{{ t('catalog.sort_name') }}</option>
        <option value="newest">{{ t('catalog.sort_newest') }}</option>
        <option value="outstanding">{{ t('credit.outstanding') }}</option>
      </select>
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="Building2" :title="t('common.no_results')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" clickable @row-click="router.push({ name: 'admin.company', params: { id: $event.id } })">
          <template #cell-name="{ row }"><p class="font-medium">{{ row.name }}</p><p class="text-xs text-muted">{{ row.contact_person }} · {{ row.phone }}</p></template>
          <template #cell-group="{ row }">{{ row.customer_group?.name ?? '—' }}</template>
          <template #cell-manager="{ row }">{{ row.account_manager?.name ?? '—' }}</template>
          <template #cell-status="{ row }"><StatusBadge :status="row.kyc.status" /></template>
          <template #cell-limit="{ row }">{{ row.credit ? formatCurrency(row.credit.credit_limit) : '—' }}</template>
          <template #cell-outstanding="{ row }"><span class="font-semibold">{{ row.credit ? formatCurrency(row.credit.outstanding_balance) : '—' }}</span></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="setQuery({ page: $event })" />
      </template>
    </div>
  </div>
</template>
