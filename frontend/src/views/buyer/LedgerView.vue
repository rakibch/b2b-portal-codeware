<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import { buyerApi } from '@/api/buyer'
import { useFormat } from '@/composables/useFormat'

const { t, locale } = useI18n()
const { formatCurrency, formatDate } = useFormat()
const iso = (d) => d.toISOString().slice(0, 10)
const from = ref(iso(new Date(Date.now() - 90 * 86400000)))
const to = ref(iso(new Date()))
const statement = ref(null)
const exporting = ref(null)

async function load() {
  statement.value = null
  statement.value = await buyerApi.ledger({ from: from.value, to: to.value })
}

async function exportAs(format) {
  exporting.value = format
  try {
    await buyerApi.exportLedger({ from: from.value, to: to.value, format })
  } finally {
    exporting.value = null
  }
}

watch([from, to, locale], load, { immediate: true })
</script>

<template>
  <div>
    <PageHeader :title="t('ledger.title')">
      <template #actions>
        <BaseButton variant="secondary" icon="FileText" :loading="exporting === 'xlsx'" @click="exportAs('xlsx')">{{ t('ledger.export_xlsx') }}</BaseButton>
        <BaseButton variant="ghost" :loading="exporting === 'csv'" @click="exportAs('csv')">{{ t('ledger.export_csv') }}</BaseButton>
      </template>
    </PageHeader>

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <div><label class="label" for="from">{{ t('common.from') }}</label><input id="from" v-model="from" type="date" class="input" :max="to" /></div>
      <div><label class="label" for="to">{{ t('common.to') }}</label><input id="to" v-model="to" type="date" class="input" :min="from" /></div>
    </div>

    <SpinnerBlock v-if="!statement" />
    <template v-else>
      <div class="mb-4 grid gap-4 sm:grid-cols-4">
        <div class="card p-4"><p class="text-xs text-muted">{{ t('ledger.opening_balance') }}</p><p class="mt-1 text-lg font-semibold tabular">{{ formatCurrency(statement.opening_balance) }}</p></div>
        <div class="card p-4"><p class="text-xs text-muted">{{ t('ledger.debit') }}</p><p class="mt-1 text-lg font-semibold tabular">{{ formatCurrency(statement.total_debit) }}</p></div>
        <div class="card p-4"><p class="text-xs text-muted">{{ t('ledger.credit') }}</p><p class="mt-1 text-lg font-semibold tabular text-emerald-700">{{ formatCurrency(statement.total_credit) }}</p></div>
        <div class="card p-4"><p class="text-xs text-muted">{{ t('ledger.closing_balance') }}</p><p class="mt-1 text-lg font-semibold tabular">{{ formatCurrency(statement.closing_balance) }}</p></div>
      </div>

      <div class="card overflow-hidden">
        <EmptyState v-if="!statement.entries.length" icon="BookOpen" :title="t('ledger.no_entries')" />
        <div v-else class="overflow-x-auto">
          <table class="w-full min-w-[720px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted">
              <tr>
                <th class="px-4 py-2.5">{{ t('common.date') }}</th>
                <th class="px-4 py-2.5">{{ t('common.type') }}</th>
                <th class="px-4 py-2.5">{{ t('ledger.description') }}</th>
                <th class="px-4 py-2.5 text-right">{{ t('ledger.debit') }}</th>
                <th class="px-4 py-2.5 text-right">{{ t('ledger.credit') }}</th>
                <th class="px-4 py-2.5 text-right">{{ t('ledger.balance') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              <tr class="bg-slate-50/50 text-muted"><td class="px-4 py-2">{{ formatDate(statement.from) }}</td><td colspan="4" class="px-4 py-2">{{ t('ledger.opening_balance') }}</td><td class="px-4 py-2 text-right tabular">{{ formatCurrency(statement.opening_balance) }}</td></tr>
              <tr v-for="e in statement.entries" :key="e.id">
                <td class="px-4 py-2.5 whitespace-nowrap">{{ formatDate(e.date) }}</td>
                <td class="px-4 py-2.5">{{ e.type.label }}</td>
                <td class="px-4 py-2.5">{{ e.description }}</td>
                <td class="px-4 py-2.5 text-right tabular">{{ Number(e.debit) ? formatCurrency(e.debit) : '' }}</td>
                <td class="px-4 py-2.5 text-right tabular text-emerald-700">{{ Number(e.credit) ? formatCurrency(e.credit) : '' }}</td>
                <td class="px-4 py-2.5 text-right font-medium tabular">{{ formatCurrency(e.balance) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
