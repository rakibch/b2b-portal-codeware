<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import DataTable from '@/components/shared/DataTable.vue'
import FilePicker from '@/components/shared/FilePicker.vue'
import PaginationBar from '@/components/shared/PaginationBar.vue'
import { buyerApi } from '@/api/buyer'
import { useApiForm } from '@/composables/useApiForm'
import { useFormat } from '@/composables/useFormat'

const { t, locale } = useI18n()
const { formatCurrency, formatDate } = useFormat()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const modal = ref(false)
const openOrders = ref([])

const today = new Date().toISOString().slice(0, 10)
const initial = { method: 'bank_transfer', order_number: null, amount: '', transaction_ref: '', bank_name: '', cheque_number: '', deposit_date: today, notes: '', slip: null }
const { form, busy, formError, fieldError, submit, reset } = useApiForm(initial)

const columns = computed(() => [
  { key: 'reference', label: t('common.reference') },
  { key: 'created_at', label: t('common.date'), class: 'hidden sm:table-cell' },
  { key: 'method', label: t('payments.method') },
  { key: 'allocations', label: t('payments.allocated_to'), class: 'hidden lg:table-cell' },
  { key: 'status', label: t('common.status') },
  { key: 'amount', label: t('common.amount'), align: 'right' },
])

async function load() {
  result.value = null
  result.value = await buyerApi.payments({ page: route.query.page || undefined })
}

async function openModal() {
  reset({ ...initial })
  if (typeof route.query.order === 'string') form.order_number = route.query.order
  const orders = await buyerApi.orders({ per_page: 50 })
  openOrders.value = orders.data.filter((o) => o.status.value !== 'cancelled' && o.payment_status.value !== 'paid')
  modal.value = true
}

async function save() {
  try {
    await submit(
      (data) => {
        const body = new FormData()
        for (const [key, value] of Object.entries(data)) {
          if (value === null || value === '') continue
          body.append(key, value)
        }
        return buyerApi.submitPayment(body)
      },
      { successMessage: t('payments.awaiting_verification') },
    )
    modal.value = false
    load()
  } catch {
    /* rendered */
  }
}

watch(() => [route.query.page, locale.value], load, { immediate: true })
</script>

<template>
  <div>
    <PageHeader :title="t('payments.title')">
      <template #actions><BaseButton icon="Upload" @click="openModal">{{ t('payments.submit') }}</BaseButton></template>
    </PageHeader>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="Wallet" :title="t('payments.no_payments')" />
      <template v-else>
        <DataTable :columns="columns" :rows="result.data" row-key="reference">
          <template #cell-reference="{ row }">
            <span class="font-mono font-medium">{{ row.reference }}</span>
            <span v-if="row.transaction_ref || row.cheque_number" class="block text-xs text-muted">{{ row.transaction_ref || row.cheque_number }}</span>
          </template>
          <template #cell-created_at="{ row }">{{ formatDate(row.created_at) }}</template>
          <template #cell-method="{ row }">{{ row.method.label }}<button v-if="row.has_slip" type="button" class="block text-xs text-brand-700 hover:underline" @click="buyerApi.paymentSlip(row.reference)">{{ t('payments.view_slip') }}</button></template>
          <template #cell-allocations="{ row }"><span class="text-xs text-muted">{{ (row.allocations ?? []).map((a) => a.invoice_number).join(', ') || row.order_number || '—' }}</span></template>
          <template #cell-status="{ row }"><StatusBadge :status="row.status" /><span v-if="row.rejection_reason" class="mt-1 block text-xs text-red-600">{{ row.rejection_reason }}</span></template>
          <template #cell-amount="{ row }"><span class="font-semibold">{{ formatCurrency(row.amount) }}</span></template>
        </DataTable>
        <PaginationBar :meta="result.meta" @page="router.replace({ query: { page: $event } })" />
      </template>
    </div>

    <BaseModal v-model:open="modal" :title="t('payments.submit')" size="lg">
      <form id="payment-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 sm:col-span-2" role="alert">{{ formError }}</div>
        <fieldset class="sm:col-span-2">
          <legend class="label">{{ t('payments.method') }}</legend>
          <div class="flex gap-4 text-sm">
            <label class="flex items-center gap-2"><input v-model="form.method" type="radio" value="bank_transfer" class="text-brand-700" />{{ t('payments.bank_transfer') }}</label>
            <label class="flex items-center gap-2"><input v-model="form.method" type="radio" value="cheque" class="text-brand-700" />{{ t('payments.cheque') }}</label>
          </div>
        </fieldset>
        <div class="sm:col-span-2">
          <label for="pay-order" class="label">{{ t('payments.for_order') }}</label>
          <select id="pay-order" v-model="form.order_number" class="input">
            <option :value="null">{{ t('payments.on_account') }}</option>
            <option v-for="o in openOrders" :key="o.order_number" :value="o.order_number">{{ o.order_number }} — {{ formatCurrency(o.totals.grand_total) }}</option>
          </select>
        </div>
        <BaseInput v-model="form.amount" :label="t('common.amount')" type="number" min="0.01" step="0.01" required :error="fieldError('amount')" />
        <BaseInput v-model="form.deposit_date" :label="t('payments.deposit_date')" type="date" :max="today" required :error="fieldError('deposit_date')" />
        <BaseInput v-model="form.bank_name" :label="t('payments.bank_name')" required :error="fieldError('bank_name')" />
        <BaseInput v-if="form.method === 'bank_transfer'" v-model="form.transaction_ref" :label="t('payments.transaction_ref')" required :error="fieldError('transaction_ref')" />
        <BaseInput v-else v-model="form.cheque_number" :label="t('payments.cheque_number')" required :error="fieldError('cheque_number')" />
        <FilePicker v-model="form.slip" class="sm:col-span-2" :label="t('payments.slip')" required :error="fieldError('slip')" />
        <div class="sm:col-span-2">
          <label for="pay-notes" class="label">{{ t('common.notes') }}</label>
          <textarea id="pay-notes" v-model="form.notes" rows="2" maxlength="1000" class="input" />
        </div>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="modal = false">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" form="payment-form" :loading="busy">{{ t('common.submit') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
