<script setup>
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { buyerApi } from '@/api/buyer'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ number: { type: String, required: true } })

const { t, locale } = useI18n()
const { formatCurrency, formatDate, formatDateTime, formatNumber } = useFormat()
const route = useRoute()
const router = useRouter()
const cart = useCartStore()
const toast = useToastStore()

const order = ref(null)
const notFound = ref(false)
const busy = ref(null)
const reasonModal = ref(null) // 'cancel' | 'reject'
const reason = ref('')
const reorderResult = ref(null)

async function load() {
  try {
    order.value = await buyerApi.order(props.number)
  } catch (e) {
    if (e.status === 404 || e.status === 403) notFound.value = true
  }
}

watch(() => [props.number, locale.value], load, { immediate: true })

const actions = computed(() => order.value?.actions ?? {})

async function run(key, fn, successKey) {
  busy.value = key
  try {
    const result = await fn()
    if (result?.data?.order_number) order.value = result.data
    if (result?.message) toast.success(result.message)
    else if (successKey) toast.success(t(successKey))
    return result
  } catch (e) {
    toast.error(e.message)
    return null
  } finally {
    busy.value = null
  }
}

async function submitReason() {
  const kind = reasonModal.value
  const fn = kind === 'cancel' ? () => buyerApi.cancelOrder(order.value.order_number, reason.value) : () => buyerApi.rejectOrder(order.value.order_number, reason.value)
  if (await run(kind, fn)) {
    reasonModal.value = null
    reason.value = ''
  }
}

async function reorder() {
  const result = await run('reorder', () => buyerApi.reorder(order.value.order_number))
  if (result) {
    cart.apply(result.cart.data ?? result.cart)
    reorderResult.value = result
  }
}

async function payOnline() {
  const result = await run('pay', () => buyerApi.payOnline(order.value.order_number))
  if (result?.redirect_url) window.location.assign(result.redirect_url)
}
</script>

<template>
  <SpinnerBlock v-if="!order && !notFound" />
  <div v-else-if="notFound" class="card"><EmptyState icon="Search" :title="t('errors.not_found_title')" /></div>
  <div v-else>
    <RouterLink :to="{ name: 'buyer.orders' }" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink"><AppIcon name="ChevronLeft" :size="16" />{{ t('orders.title') }}</RouterLink>

    <div v-if="route.query.placed" class="mb-4 flex items-center gap-3 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">
      <AppIcon name="CheckCircle2" :size="18" /> {{ t('checkout.success_body', { number: order.order_number }) }}
    </div>

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="font-mono text-2xl font-semibold">{{ order.order_number }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <StatusBadge :status="order.status" />
          <StatusBadge :status="order.payment_status" />
          <StatusBadge v-if="order.approval_status.value !== 'not_required'" :status="order.approval_status" />
          <span class="text-sm text-muted">{{ formatDateTime(order.created_at) }} · {{ order.placed_by?.name }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <template v-if="actions.can_approve">
          <BaseButton icon="Check" :loading="busy === 'approve'" @click="run('approve', () => buyerApi.approveOrder(order.order_number))">{{ t('orders.approve') }}</BaseButton>
          <BaseButton variant="secondary" @click="reasonModal = 'reject'">{{ t('orders.reject') }}</BaseButton>
        </template>
        <BaseButton v-if="actions.can_pay_online" icon="Wallet" :loading="busy === 'pay'" @click="payOnline">{{ t('orders.pay_online') }}</BaseButton>
        <BaseButton v-if="actions.can_reorder" variant="secondary" icon="ShoppingCart" :loading="busy === 'reorder'" @click="reorder">{{ t('orders.reorder') }}</BaseButton>
        <BaseButton v-if="actions.can_cancel" variant="ghost" icon="XCircle" @click="reasonModal = 'cancel'">{{ t('orders.cancel') }}</BaseButton>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <section class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-sm">
              <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted">
                <tr>
                  <th class="px-4 py-2.5">{{ t('common.product') }}</th>
                  <th class="px-4 py-2.5 text-right">{{ t('common.qty') }}</th>
                  <th class="px-4 py-2.5 text-right">{{ t('common.unit_price') }}</th>
                  <th class="px-4 py-2.5 text-right">{{ t('common.total') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-line">
                <tr v-for="item in order.items" :key="item.id">
                  <td class="px-4 py-3">
                    <p class="font-medium">{{ item.name }}</p>
                    <p class="text-xs text-muted"><span class="font-mono">{{ item.sku }}</span> · {{ item.price_source.label }}</p>
                  </td>
                  <td class="px-4 py-3 text-right tabular">
                    {{ formatNumber(item.quantity) }} {{ item.unit }}
                    <span v-if="item.dispatched_quantity" class="block text-xs text-muted">{{ t('orders.dispatched_qty') }}: {{ formatNumber(item.dispatched_quantity) }}</span>
                  </td>
                  <td class="px-4 py-3 text-right tabular">{{ formatCurrency(item.unit_price) }}</td>
                  <td class="px-4 py-3 text-right font-medium tabular">{{ formatCurrency(item.line_total) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <dl class="space-y-1.5 border-t border-line bg-slate-50 px-4 py-4 text-sm">
            <div class="flex justify-between"><dt class="text-muted">{{ t('cart.subtotal') }}</dt><dd class="tabular">{{ formatCurrency(order.totals.subtotal) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">{{ t('cart.vat') }}</dt><dd class="tabular">{{ formatCurrency(order.totals.vat_total) }}</dd></div>
            <div class="flex justify-between"><dt class="text-muted">{{ t('cart.shipping') }}</dt><dd class="tabular">{{ formatCurrency(order.totals.shipping_total) }}</dd></div>
            <div class="flex justify-between text-base font-semibold"><dt>{{ t('cart.grand_total') }}</dt><dd class="tabular">{{ formatCurrency(order.totals.grand_total) }}</dd></div>
            <div class="flex justify-between text-emerald-700"><dt>{{ t('invoices.paid') }}</dt><dd class="tabular">{{ formatCurrency(order.totals.amount_paid) }}</dd></div>
          </dl>
        </section>

        <section v-if="order.shipments?.length" class="card">
          <h2 class="border-b border-line px-5 py-3 text-sm font-semibold">{{ t('orders.shipments') }}</h2>
          <ul class="divide-y divide-line">
            <li v-for="s in order.shipments" :key="s.challan_number" class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center">
              <div class="min-w-0 flex-1">
                <p class="font-mono text-sm font-medium">{{ s.challan_number }}</p>
                <p class="text-xs text-muted">{{ s.carrier }} <span v-if="s.vehicle_number">· {{ s.vehicle_number }}</span> <span v-if="s.dispatched_at">· {{ formatDate(s.dispatched_at) }}</span></p>
              </div>
              <StatusBadge :status="s.status" />
              <div class="flex gap-1">
                <BaseButton size="sm" variant="ghost" icon="FileText" @click="buyerApi.challanPdf(s.challan_number, 'en')">EN</BaseButton>
                <BaseButton size="sm" variant="ghost" icon="FileText" @click="buyerApi.challanPdf(s.challan_number, 'bn')">বাং</BaseButton>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <aside class="space-y-6">
        <section v-if="order.invoice" class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('orders.invoice') }}</h2>
          <p class="mt-2 font-mono text-sm">{{ order.invoice.invoice_number }}</p>
          <p class="text-xs text-muted">{{ t('invoices.due_date') }}: {{ formatDate(order.invoice.due_date) }}</p>
          <div class="mt-2"><StatusBadge :status="order.invoice.is_overdue ? 'overdue' : order.invoice.status.value" :label="order.invoice.is_overdue ? t('invoices.overdue') : order.invoice.status.label" /></div>
          <div class="mt-4 flex gap-2">
            <BaseButton size="sm" variant="secondary" icon="FileText" @click="buyerApi.invoicePdf(order.invoice.invoice_number, 'en')">{{ t('invoices.pdf_en') }}</BaseButton>
            <BaseButton size="sm" variant="secondary" icon="FileText" @click="buyerApi.invoicePdf(order.invoice.invoice_number, 'bn')">{{ t('invoices.pdf_bn') }}</BaseButton>
          </div>
        </section>

        <section class="card p-5 text-sm">
          <h2 class="font-semibold">{{ t('orders.shipping_to') }}</h2>
          <p class="mt-2 font-medium">{{ order.shipping_address.label }}</p>
          <p class="text-muted">{{ order.shipping_address.contact_name }} · {{ order.shipping_address.contact_phone }}</p>
          <p class="text-muted">{{ [order.shipping_address.address_line1, order.shipping_address.area, order.shipping_address.city].filter(Boolean).join(', ') }}</p>
          <p class="mt-3 text-xs text-muted">{{ t('checkout.payment_method') }}: {{ order.payment_method.label }}</p>
          <p v-if="order.po_number" class="text-xs text-muted">{{ t('checkout.po_number') }}: {{ order.po_number }}</p>
          <p v-if="order.rfq_reference" class="text-xs text-muted">{{ t('rfq.reference') }}: {{ order.rfq_reference }}</p>
        </section>

        <section class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('orders.timeline') }}</h2>
          <ol class="mt-4 space-y-4 border-l border-line pl-4">
            <li v-for="(h, i) in order.history" :key="i" class="relative">
              <span class="absolute top-1.5 -left-[21px] h-2.5 w-2.5 rounded-full bg-brand-600 ring-4 ring-white" />
              <p class="text-sm font-medium">{{ h.to.label }}</p>
              <p class="text-xs text-muted">{{ formatDateTime(h.at) }}<span v-if="h.by"> · {{ h.by }}</span></p>
              <p v-if="h.comment" class="mt-0.5 text-xs text-slate-600">{{ h.comment }}</p>
            </li>
          </ol>
          <p v-if="order.cancellation_reason" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ order.cancellation_reason }}</p>
        </section>
      </aside>
    </div>

    <BaseModal :open="Boolean(reasonModal)" :title="reasonModal === 'cancel' ? t('orders.cancel') : t('orders.reject')" size="sm" @update:open="reasonModal = $event ? reasonModal : null">
      <p v-if="reasonModal === 'cancel'" class="mb-3 text-sm text-muted">{{ t('orders.cancel_confirm') }}</p>
      <label for="reason" class="label">{{ t('common.reason') }}</label>
      <textarea id="reason" v-model="reason" rows="3" minlength="5" maxlength="2000" class="input" required />
      <template #footer>
        <BaseButton variant="secondary" @click="reasonModal = null">{{ t('common.close') }}</BaseButton>
        <BaseButton variant="danger" :loading="busy === reasonModal" :disabled="reason.trim().length < 5" @click="submitReason">{{ t('common.confirm') }}</BaseButton>
      </template>
    </BaseModal>

    <BaseModal :open="Boolean(reorderResult)" :title="t('orders.reorder')" size="sm" @update:open="reorderResult = $event ? reorderResult : null">
      <template v-if="reorderResult">
        <p class="text-sm">{{ reorderResult.message }}</p>
        <ul v-if="reorderResult.skipped.length" class="mt-3 space-y-1 text-xs text-red-600">
          <li v-for="s in reorderResult.skipped" :key="s.sku"><span class="font-mono">{{ s.sku }}</span> — {{ s.message }}</li>
        </ul>
      </template>
      <template #footer>
        <BaseButton variant="secondary" @click="reorderResult = null">{{ t('common.close') }}</BaseButton>
        <BaseButton @click="router.push({ name: 'buyer.cart' })">{{ t('nav.cart') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
