<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import PaginationBar from '@/components/shared/PaginationBar.vue'
import ReasonDialog from '@/components/shared/ReasonDialog.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const { t, locale } = useI18n()
const { formatCurrency, formatDate } = useFormat()
const auth = useAuthStore()
const toast = useToastStore()
const route = useRoute()
const router = useRouter()
const result = ref(null)
const busy = ref(null)
const rejecting = ref(null)
const rejectError = ref(null)
const status = computed(() => String(route.query.status ?? 'pending'))

async function load() {
  result.value = null
  result.value = await adminApi.payments({ status: status.value === 'all' ? undefined : status.value, page: route.query.page || undefined })
}

async function verify(p) {
  busy.value = p.reference
  try {
    const r = await adminApi.verifyPayment(p.reference)
    toast.success(r.message)
    await load()
  } catch (e) {
    toast.error(e.message)
  } finally {
    busy.value = null
  }
}

async function reject(reason) {
  busy.value = 'reject'
  rejectError.value = null
  try {
    const r = await adminApi.rejectPayment(rejecting.value.reference, reason)
    toast.success(r.message)
    rejecting.value = null
    await load()
  } catch (e) {
    rejectError.value = e.message
  } finally {
    busy.value = null
  }
}

watch(() => [route.query, locale.value], load, { immediate: true, deep: true })
</script>

<template>
  <div>
    <PageHeader :title="t('nav.payments')" />
    <div class="mb-4 flex flex-wrap gap-2" role="tablist">
      <button v-for="s in ['pending', 'verified', 'rejected', 'all']" :key="s" type="button" role="tab" :aria-selected="status === s" class="rounded-full px-3 py-1 text-sm" :class="status === s ? 'bg-brand-700 text-white' : 'bg-white text-slate-600 ring-1 ring-line hover:bg-slate-50'" @click="router.replace({ query: { status: s } })">
        {{ s === 'all' ? t('common.all') : t(`payment_status.${s}`) }}
      </button>
    </div>
    <div class="card">
      <SpinnerBlock v-if="!result" />
      <EmptyState v-else-if="!result.data.length" icon="Wallet" :title="t('payments.no_payments')" />
      <ul v-else class="divide-y divide-line">
        <li v-for="p in result.data" :key="p.reference" class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center">
          <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-center gap-2"><span class="font-mono text-sm font-medium">{{ p.reference }}</span><StatusBadge :status="p.status" /></p>
            <p class="mt-0.5 text-sm">{{ p.company?.name }}<span v-if="p.order_number" class="text-muted"> · {{ p.order_number }}</span></p>
            <p class="text-xs text-muted">
              {{ p.method.label }}<span v-if="p.bank_name"> · {{ p.bank_name }}</span><span v-if="p.transaction_ref"> · {{ t('payments.transaction_ref') }}: {{ p.transaction_ref }}</span><span v-if="p.cheque_number"> · {{ t('payments.cheque_number') }}: {{ p.cheque_number }}</span>
              <span v-if="p.deposit_date"> · {{ formatDate(p.deposit_date) }}</span>
            </p>
            <p v-if="p.allocations?.length" class="text-xs text-emerald-700">{{ t('payments.allocated_to') }}: {{ p.allocations.map((a) => `${a.invoice_number} (${formatCurrency(a.amount)})`).join(', ') }}</p>
            <p v-if="p.rejection_reason" class="text-xs text-red-600">{{ p.rejection_reason }}</p>
          </div>
          <p class="text-lg font-semibold tabular">{{ formatCurrency(p.amount) }}</p>
          <div class="flex gap-2">
            <BaseButton v-if="p.has_slip" size="sm" variant="ghost" icon="Eye" @click="adminApi.paymentSlip(p.reference)">{{ t('payments.view_slip') }}</BaseButton>
            <template v-if="p.status.value === 'pending' && auth.can('payments.verify')">
              <BaseButton size="sm" icon="Check" :loading="busy === p.reference" @click="verify(p)">{{ t('payments.verify') }}</BaseButton>
              <BaseButton size="sm" variant="secondary" @click="rejecting = p">{{ t('payments.reject') }}</BaseButton>
            </template>
          </div>
        </li>
      </ul>
      <PaginationBar v-if="result" :meta="result.meta" @page="router.replace({ query: { ...route.query, page: $event } })" />
    </div>
    <ReasonDialog :open="Boolean(rejecting)" :title="t('payments.reject')" :loading="busy === 'reject'" :error="rejectError" @update:open="!$event && (rejecting = null)" @confirm="reject" />
  </div>
</template>
