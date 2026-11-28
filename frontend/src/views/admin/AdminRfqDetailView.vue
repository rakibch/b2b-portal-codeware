<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReasonDialog from '@/components/shared/ReasonDialog.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ reference: { type: String, required: true } })
const { t } = useI18n()
const { formatCurrency, formatDate, formatNumber } = useFormat()
const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const rfq = ref(null)
const prices = reactive({})
const quote = reactive({ valid_until: '', quote_notes: '', internal_notes: '' })
const busy = ref(null)
const error = ref(null)
const declining = ref(false)

const canQuote = computed(() => auth.can('rfqs.quote') && ['submitted', 'under_review', 'quoted'].includes(rfq.value?.status.value))
const quotedTotal = computed(() => (rfq.value?.items ?? []).reduce((sum, i) => sum + (Number(prices[i.id]) || 0) * i.quantity, 0))

async function load() {
  rfq.value = await adminApi.rfq(props.reference)
  for (const item of rfq.value.items) prices[item.id] = item.quoted_unit_price ?? item.target_unit_price ?? item.product?.list_price ?? ''
  const defaultValidity = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10)
  Object.assign(quote, { valid_until: rfq.value.valid_until ?? defaultValidity, quote_notes: rfq.value.quote_notes ?? '', internal_notes: rfq.value.internal_notes ?? '' })
}

async function act(key, fn) {
  busy.value = key
  error.value = null
  try {
    const result = await fn()
    if (result?.message) toast.success(result.message)
    await load()
    return true
  } catch (e) {
    error.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
    return false
  } finally {
    busy.value = null
  }
}

function sendQuote() {
  return act('quote', () => adminApi.quoteRfq(props.reference, {
    items: rfq.value.items.map((i) => ({ id: i.id, quoted_unit_price: prices[i.id] })),
    valid_until: quote.valid_until,
    quote_notes: quote.quote_notes || null,
    internal_notes: quote.internal_notes || null,
  }))
}

async function decline(reason) {
  if (await act('decline', () => adminApi.declineRfq(props.reference, reason))) declining.value = false
}

onMounted(load)
</script>

<template>
  <SpinnerBlock v-if="!rfq" />
  <div v-else>
    <button type="button" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink" @click="router.back()"><AppIcon name="ChevronLeft" :size="16" />{{ t('nav.rfqs') }}</button>
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="font-mono text-2xl font-semibold">{{ rfq.reference }}</h1>
        <p class="mt-1 text-sm">{{ rfq.company?.name }} <span class="text-muted">· {{ rfq.requested_by?.name }} · {{ formatDate(rfq.created_at) }}</span></p>
        <div class="mt-2"><StatusBadge :status="rfq.status" /></div>
      </div>
      <div class="flex flex-wrap gap-2">
        <BaseButton v-if="auth.can('rfqs.quote') && rfq.status.value === 'submitted'" variant="secondary" :loading="busy === 'review'" @click="act('review', () => adminApi.startRfqReview(props.reference))">{{ t('rfq.start_review') }}</BaseButton>
        <BaseButton v-if="auth.can('rfqs.quote') && ['submitted', 'under_review'].includes(rfq.status.value)" variant="ghost" icon="XCircle" @click="declining = true">{{ t('rfq.decline') }}</BaseButton>
        <BaseButton v-if="rfq.converted_order_id && $router.hasRoute('admin.orders')" variant="secondary" :to="{ name: 'admin.orders', query: { q: rfq.reference } }">{{ t('nav.orders') }}</BaseButton>
      </div>
    </div>

    <div v-if="error" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ error }}</div>

    <section class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm">
          <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted">
            <tr><th class="px-4 py-2.5">{{ t('common.product') }}</th><th class="px-4 py-2.5 text-right">{{ t('common.qty') }}</th><th class="px-4 py-2.5 text-right">{{ t('catalog.list_price') }}</th><th class="px-4 py-2.5 text-right">{{ t('rfq.target_price') }}</th><th class="w-40 px-4 py-2.5 text-right">{{ t('rfq.quoted_price') }}</th></tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="item in rfq.items" :key="item.id">
              <td class="px-4 py-3"><p class="font-medium">{{ item.product?.name }}</p><p class="font-mono text-xs text-muted">{{ item.product?.sku }}</p><p v-if="item.notes" class="text-xs text-muted">{{ item.notes }}</p></td>
              <td class="px-4 py-3 text-right tabular">{{ formatNumber(item.quantity) }} {{ item.product?.unit }}</td>
              <td class="px-4 py-3 text-right tabular text-muted">{{ formatCurrency(item.product?.list_price) }}</td>
              <td class="px-4 py-3 text-right tabular">{{ item.target_unit_price ? formatCurrency(item.target_unit_price) : '—' }}</td>
              <td class="px-4 py-3 text-right">
                <input v-if="canQuote" v-model="prices[item.id]" type="number" min="0.01" step="0.01" class="input text-right tabular" :aria-label="`${t('rfq.quoted_price')} ${item.product?.sku}`" />
                <span v-else class="font-semibold tabular">{{ item.quoted_unit_price ? formatCurrency(item.quoted_unit_price) : '—' }}</span>
              </td>
            </tr>
          </tbody>
          <tfoot class="border-t border-line bg-slate-50"><tr><td colspan="4" class="px-4 py-3 text-right font-semibold">{{ t('rfq.quoted_total') }} <span class="text-xs font-normal text-muted">({{ t('rfq.excl_vat') }})</span></td><td class="px-4 py-3 text-right font-semibold tabular">{{ formatCurrency(canQuote ? quotedTotal : rfq.quoted_total) }}</td></tr></tfoot>
        </table>
      </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <section v-if="rfq.buyer_notes" class="card p-5"><h2 class="text-sm font-semibold">{{ t('common.notes') }}</h2><p class="mt-2 text-sm whitespace-pre-line text-slate-600">{{ rfq.buyer_notes }}</p></section>
      <section v-if="canQuote" class="card space-y-4 p-5">
        <h2 class="text-sm font-semibold">{{ t('rfq.prepare_quote') }}</h2>
        <BaseInput v-model="quote.valid_until" :label="t('rfq.valid_until')" type="date" :min="new Date().toISOString().slice(0, 10)" required />
        <div><label for="qn" class="label">{{ t('rfq.quote_notes') }}</label><textarea id="qn" v-model="quote.quote_notes" rows="2" maxlength="2000" class="input" /></div>
        <div><label for="in" class="label">{{ t('admin.internal_notes') }}</label><textarea id="in" v-model="quote.internal_notes" rows="2" maxlength="2000" class="input" /></div>
        <BaseButton block icon="MessageSquareQuote" :loading="busy === 'quote'" :disabled="!quote.valid_until || rfq.items.some((i) => !(Number(prices[i.id]) > 0))" @click="sendQuote">{{ t('rfq.send_quote') }}</BaseButton>
      </section>
      <section v-else-if="rfq.quote_notes" class="card p-5"><h2 class="text-sm font-semibold">{{ t('rfq.quote_notes') }}</h2><p class="mt-2 text-sm text-slate-600">{{ rfq.quote_notes }}</p><p class="mt-2 text-xs text-muted">{{ t('rfq.valid_until') }}: {{ formatDate(rfq.valid_until) }}</p></section>
    </div>

    <ReasonDialog v-model:open="declining" :title="t('rfq.decline')" :loading="busy === 'decline'" :error="error" @confirm="decline" />
  </div>
</template>
