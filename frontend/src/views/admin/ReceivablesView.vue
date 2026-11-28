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
import { useFormat } from '@/composables/useFormat'

const { t, locale } = useI18n()
const { formatCurrency, formatDate } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const status = computed(() => String(route.query.status ?? 'outstanding'))

const columns = computed(() => [
  { key: 'invoice_number', label: t('invoices.invoice_number') },
  { key: 'company', label: t('common.company') },
  { key: 'due_date', label: t('invoices.due_date') },
  { key: 'status', label: t('common.status') },
  { key: 'grand_total', label: t('common.total'), align: 'right', class: 'hidden md:table-cell' },
  { key: 'balance_due', label: t('invoices.balance_due'), align: 'right' },
  { key: 'pdf', label: '', align: 'right' },
])

async function load() {
  result.value = null
  result.value = await adminApi.invoices({ status: status.value === 'all' ? undefined : status.value, page: route.query.page || undefined })
}

watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('nav.receivables')" />
    <div class="mb-4 flex flex-wrap gap-2" role="tablist">
      <button v-for="s in ['outstanding', 'overdue', 'paid', 'void', 'all']" :key="s" type="button" role="tab" :aria-selected="status === s" class="rounded-full px-3 py-1 text-sm" :class="status === s ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-line hover:bg-slate-50'" @click="router.replace({ query: { status: s } })">
        {{ { outstanding: t('invoices.outstanding'), overdue: t('invoices.overdue'), paid: t('invoices.paid'), void: t('admin.void'), all: t('common.all') }[s] }}
      </button>
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="FileText" :title="t('invoices.no_invoices')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" row-key="invoice_number">
          <template #cell-invoice_number="{ row }"><span class="font-mono font-medium">{{ row.invoice_number }}</span><RouterLink :to="{ name: 'admin.order', params: { number: row.order_number } }" class="block text-xs text-brand-700 hover:underline">{{ row.order_number }}</RouterLink></template>
          <template #cell-company="{ row }"><RouterLink :to="{ name: 'admin.company', params: { id: row.company.id } }" class="hover:text-brand-700">{{ row.company.name }}</RouterLink></template>
          <template #cell-due_date="{ row }"><span :class="row.is_overdue ? 'font-semibold text-red-600' : ''">{{ formatDate(row.due_date) }}</span></template>
          <template #cell-status="{ row }"><StatusBadge :status="row.is_overdue ? 'overdue' : row.status.value" :label="row.is_overdue ? t('invoices.overdue') : row.status.label" /></template>
          <template #cell-grand_total="{ row }">{{ formatCurrency(row.grand_total) }}</template>
          <template #cell-balance_due="{ row }"><span class="font-semibold">{{ formatCurrency(row.balance_due) }}</span></template>
          <template #cell-pdf="{ row }"><BaseButton size="sm" variant="ghost" icon="FileText" @click="adminApi.invoicePdf(row.invoice_number, locale)">PDF</BaseButton></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
      </template>
    </div>
  </div>
</template>
