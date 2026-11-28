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
import { buyerApi } from '@/api/buyer'
import { useFormat } from '@/composables/useFormat'

const { t, locale } = useI18n()
const { formatCurrency, formatDate } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)

const tabs = computed(() => [
  { value: '', label: t('common.all') },
  { value: 'outstanding', label: t('invoices.outstanding') },
  { value: 'overdue', label: t('invoices.overdue') },
  { value: 'paid', label: t('invoices.paid') },
])
const columns = computed(() => [
  { key: 'invoice_number', label: t('invoices.invoice_number') },
  { key: 'issue_date', label: t('invoices.issue_date'), class: 'hidden sm:table-cell' },
  { key: 'due_date', label: t('invoices.due_date') },
  { key: 'status', label: t('common.status') },
  { key: 'grand_total', label: t('common.total'), align: 'right', class: 'hidden md:table-cell' },
  { key: 'balance_due', label: t('invoices.balance_due'), align: 'right' },
  { key: 'pdf', label: '', align: 'right' },
])

async function load() {
  result.value = null
  result.value = await buyerApi.invoices({ status: route.query.status || undefined, page: route.query.page || undefined })
}

watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('invoices.title')" />
    <div class="mb-4 flex flex-wrap gap-2" role="tablist">
      <button v-for="tab in tabs" :key="tab.value" type="button" role="tab" :aria-selected="(route.query.status ?? '') === tab.value" class="rounded-full px-3 py-1 text-sm" :class="(route.query.status ?? '') === tab.value ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-line hover:bg-slate-50'" @click="router.replace({ query: tab.value ? { status: tab.value } : {} })">{{ tab.label }}</button>
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="FileText" :title="t('invoices.no_invoices')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" row-key="invoice_number">
          <template #cell-invoice_number="{ row }">
            <span class="font-mono font-medium">{{ row.invoice_number }}</span>
            <RouterLink v-if="row.order_number" :to="{ name: 'buyer.order', params: { number: row.order_number } }" class="block text-xs text-brand-700 hover:underline">{{ row.order_number }}</RouterLink>
          </template>
          <template #cell-issue_date="{ row }">{{ formatDate(row.issue_date) }}</template>
          <template #cell-due_date="{ row }"><span :class="row.is_overdue ? 'font-semibold text-red-600' : ''">{{ formatDate(row.due_date) }}</span></template>
          <template #cell-status="{ row }"><StatusBadge :status="row.is_overdue ? 'overdue' : row.status.value" :label="row.is_overdue ? t('invoices.overdue') : row.status.label" /></template>
          <template #cell-grand_total="{ row }">{{ formatCurrency(row.grand_total) }}</template>
          <template #cell-balance_due="{ row }"><span class="font-semibold">{{ formatCurrency(row.balance_due) }}</span></template>
          <template #cell-pdf="{ row }">
            <div class="flex justify-end gap-1">
              <BaseButton size="sm" variant="ghost" icon="FileText" @click="buyerApi.invoicePdf(row.invoice_number, 'en')">EN</BaseButton>
              <BaseButton size="sm" variant="ghost" icon="FileText" @click="buyerApi.invoicePdf(row.invoice_number, 'bn')">বাং</BaseButton>
            </div>
          </template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
      </template>
    </div>
  </div>
</template>
