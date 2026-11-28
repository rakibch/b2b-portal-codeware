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
const { formatDate } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const search = ref(String(route.query.q ?? ''))
const status = computed(() => String(route.query.status ?? 'pending'))

const tabs = ['pending', 'rejected', 'approved', 'suspended']
const columns = computed(() => [
  { key: 'name', label: t('common.company') },
  { key: 'business_type', label: t('register.business_type'), class: 'hidden md:table-cell' },
  { key: 'trade_license_no', label: t('register.trade_license_no'), class: 'hidden lg:table-cell' },
  { key: 'submitted', label: t('kyc.submitted_col') },
  { key: 'status', label: t('common.status') },
])

async function load() {
  result.value = null
  result.value = await adminApi.kycQueue({ status: status.value, q: route.query.q || undefined, page: route.query.page || undefined })
}

const onSearch = debounce((q) => router.replace({ query: { ...route.query, q: q || undefined, page: undefined } }), 350)
watch(search, onSearch)
watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('nav.kyc_queue')" />
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div class="flex flex-wrap gap-2" role="tablist">
        <button v-for="tab in tabs" :key="tab" type="button" role="tab" :aria-selected="status === tab" class="rounded-full px-3 py-1 text-sm" :class="status === tab ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-line hover:bg-slate-50'" @click="router.replace({ query: { status: tab } })">
          {{ t(`kyc_status.${tab}`) }}
        </button>
      </div>
      <input v-model="search" type="search" class="input sm:max-w-xs" :placeholder="t('common.search_placeholder')" :aria-label="t('common.search')" />
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="ShieldCheck" :title="t('common.no_results')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" clickable @row-click="router.push({ name: 'admin.kyc-review', params: { id: $event.id } })">
          <template #cell-name="{ row }"><p class="font-medium">{{ row.name }}</p><p class="text-xs text-muted">{{ row.contact_person }} · {{ row.phone }}</p></template>
          <template #cell-business_type="{ row }">{{ row.business_type.label }}</template>
          <template #cell-trade_license_no="{ row }"><span class="font-mono text-xs">{{ row.trade_license_no }}</span></template>
          <template #cell-submitted="{ row }">{{ formatDate(row.kyc.submitted_at) }}</template>
          <template #cell-status="{ row }"><StatusBadge :status="row.kyc.status" /></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
      </template>
    </div>
  </div>
</template>
