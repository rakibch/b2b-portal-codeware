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
import { useFormat } from '@/composables/useFormat'

const { t, locale } = useI18n()
const { formatDate, formatNumber } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const status = computed(() => String(route.query.status ?? ''))

const columns = computed(() => [
  { key: 'reference', label: t('rfq.reference') },
  { key: 'company', label: t('common.company') },
  { key: 'created_at', label: t('common.date'), class: 'hidden md:table-cell' },
  { key: 'assigned', label: t('kyc.assign_manager'), class: 'hidden lg:table-cell' },
  { key: 'status', label: t('common.status') },
])

async function load() {
  result.value = null
  result.value = await adminApi.rfqs({ status: status.value || undefined, mine: route.query.mine === '1' ? 1 : undefined, page: route.query.page || undefined })
}

watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('nav.rfqs')" />
    <div class="mb-4 flex flex-wrap items-center gap-2" role="tablist">
      <button v-for="s in ['', 'submitted', 'under_review', 'quoted', 'accepted', 'converted']" :key="s" type="button" role="tab" :aria-selected="status === s" class="rounded-full px-3 py-1 text-sm" :class="status === s ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-line hover:bg-slate-50'" @click="router.replace({ query: { ...route.query, status: s || undefined, page: undefined } })">
        {{ s ? t(`rfq_status.${s}`) : t('common.all') }}
      </button>
      <label class="ml-auto flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" :checked="route.query.mine === '1'" @change="router.replace({ query: { ...route.query, mine: $event.target.checked ? '1' : undefined } })" />{{ t('admin.assigned_to_me') }}</label>
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="MessageSquareQuote" :title="t('rfq.no_rfqs')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" row-key="reference" clickable @row-click="router.push({ name: 'admin.rfq', params: { reference: $event.reference } })">
          <template #cell-reference="{ row }"><span class="font-mono font-medium">{{ row.reference }}</span><span class="block text-xs text-muted">{{ t('orders.items_count', { count: formatNumber(row.items_count) }) }}</span></template>
          <template #cell-company="{ row }">{{ row.company?.name }}<span class="block text-xs text-muted">{{ row.requested_by?.name }}</span></template>
          <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
          <template #cell-assigned="{ row }">{{ row.assigned_to?.name ?? '—' }}</template>
          <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
      </template>
    </div>
  </div>
</template>
