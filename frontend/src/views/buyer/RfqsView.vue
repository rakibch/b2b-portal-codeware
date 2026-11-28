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
const { formatDate, formatNumber } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)

const columns = computed(() => [
  { key: 'reference', label: t('rfq.reference') },
  { key: 'created_at', label: t('common.date') },
  { key: 'items', label: t('common.products'), class: 'hidden sm:table-cell' },
  { key: 'valid_until', label: t('rfq.valid_until'), class: 'hidden md:table-cell' },
  { key: 'status', label: t('common.status') },
])

async function load() {
  result.value = null
  result.value = await buyerApi.rfqs({ status: route.query.status || undefined, page: route.query.page || undefined })
}

watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('rfq.title')">
      <template #actions><BaseButton icon="Plus" :to="{ name: 'buyer.rfq-new' }">{{ t('rfq.new') }}</BaseButton></template>
    </PageHeader>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="MessageSquareQuote" :title="t('rfq.no_rfqs')">
        <BaseButton :to="{ name: 'buyer.rfq-new' }">{{ t('rfq.new') }}</BaseButton>
      </EmptyState>
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" row-key="reference" clickable @row-click="router.push({ name: 'buyer.rfq', params: { reference: $event.reference } })">
          <template #cell-reference="{ row }"><span class="font-mono font-medium">{{ row.reference }}</span></template>
          <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
          <template #cell-items="{ row }">{{ formatNumber(row.items_count) }}</template>
          <template #cell-valid_until="{ row }"><span :class="row.is_expired ? 'text-red-600' : ''">{{ formatDate(row.valid_until) }}</span></template>
          <template #cell-status="{ row }"><StatusBadge :status="row.status" /></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
      </template>
    </div>
  </div>
</template>
