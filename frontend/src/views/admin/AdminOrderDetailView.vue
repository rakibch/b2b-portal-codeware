<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReasonDialog from '@/components/shared/ReasonDialog.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ number: { type: String, required: true } })
const { t, locale } = useI18n()
const { formatCurrency, formatDate, formatDateTime, formatNumber } = useFormat()
const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const order = ref(null)
const busy = ref(null)
const modal = ref(null) // shipment | cash | cancel
const formError = ref(null)
const shipment = reactive({ quantities: {}, carrier: '', vehicle_number: '', driver_name: '', driver_phone: '', tracking_number: '', notes: '' })
const cash = reactive({ amount: '', notes: '' })

const status = computed(() => order.value?.status.value)
const canUpdate = computed(() => auth.can('orders.update_status'))
const canFulfil = computed(() => auth.can('orders.fulfil'))
const canCash = computed(() => auth.can('payments.verify'))
const packedQty = computed(() => {
  const map = {}
  for (const s of order.value?.shipments ?? []) if (s.status.value === 'packing') for (const l of s.items) map[l.order_item_id] = (map[l.order_item_id] ?? 0) + l.quantity
  return map
})
const shippable = computed(() => (order.value?.items ?? []).map((i) => ({ ...i, available: i.remaining_quantity - (packedQty.value[i.id] ?? 0) })).filter((i) => i.available > 0))

async function load() {
  order.value = await adminApi.order(props.number)
}

async function run(key, fn) {
  busy.value = key
  formError.value = null
  try {
    const result = await fn()
    if (result?.message) toast.success(result.message)
    modal.value = null
    await load()
  } catch (e) {
    formError.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
    if (!modal.value) toast.error(formError.value)
  } finally {
    busy.value = null
  }
}

function openShipment() {
  shipment.quantities = Object.fromEntries(shippable.value.map((i) => [i.id, i.available]))
  formError.value = null
  modal.value = 'shipment'
}

function createShipment() {
  const items = Object.entries(shipment.quantities).filter(([, q]) => Number(q) > 0).map(([id, q]) => ({ order_item_id: Number(id), quantity: Number(q) }))
  const { quantities, ...details } = shipment
  return run('shipment', () => adminApi.createShipment(order.value.order_number, { items, ...Object.fromEntries(Object.entries(details).filter(([, v]) => v)) }))
}

function openCash() {
  cash.amount = String(Math.max(0, Number(order.value.totals.grand_total) - Number(order.value.totals.amount_paid)).toFixed(2))
  cash.notes = ''
  formError.value = null
  modal.value = 'cash'
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <SpinnerBlock v-if="!order" />
  <div v-else>
    <button type="button" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink" @click="router.back()"><AppIcon name="ChevronLeft" :size="16" />{{ t('nav.orders') }}</button>

    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="font-mono text-2xl font-semibold">{{ order.order_number }}</h1>
        <p class="mt-1 text-sm">
          <RouterLink v-if="$router.hasRoute('admin.company') && auth.can('companies.view')" :to="{ name: 'admin.company', params: { id: order.company.id } }" class="font-medium text-brand-700 hover:underline">{{ order.company.name }}</RouterLink>
          <span v-else class="font-medium">{{ order.company.name }}</span>
          <span class="text-muted"> · {{ order.placed_by?.name }}<span v-if="order.created_by_staff"> ({{ t('orders.on_behalf') }}: {{ order.created_by_staff.name }})</span></span>
        </p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <StatusBadge :status="order.status" />
          <StatusBadge :status="order.payment_status" />
          <StatusBadge v-if="order.approval_status.value !== 'not_required'" :status="order.approval_status" />
          <span class="text-sm text-muted">{{ formatDateTime(order.created_at) }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <BaseButton v-if="canUpdate && status === 'pending'" icon="Check" :loading="busy === 'confirm'" @click="run('confirm', () => adminApi.updateOrderStatus(order.order_number, 'confirmed'))">{{ t('orders.confirm_order') }}</BaseButton>
        <BaseButton v-if="canUpdate && status === 'confirmed'" variant="secondary" :loading="busy === 'process'" @click="run('process', () => adminApi.updateOrderStatus(order.order_number, 'processing'))">{{ t('orders.mark_processing') }}</BaseButton>
        <BaseButton v-if="canFulfil && ['confirmed', 'processing', 'partially_dispatched'].includes(status) && shippable.length" icon="Truck" @click="openShipment">{{ t('orders.create_shipment') }}</BaseButton>
        <BaseButton v-if="canCash && order.payment_method.value === 'cod' && order.payment_status.value !== 'paid' && status !== 'cancelled'" variant="secondary" icon="Wallet" @click="openCash">{{ t('orders.record_cash') }}</BaseButton>
        <BaseButton v-if="auth.can('orders.cancel') && ['pending', 'confirmed', 'processing'].includes(status)" variant="ghost" icon="XCircle" @click="modal = 'cancel'">{{ t('orders.cancel') }}</BaseButton>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <section class="card overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-sm">
              <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted">
                <tr><th class="px-4 py-2.5">{{ t('common.product') }}</th><th class="px-4 py-2.5 text-right">{{ t('common.qty') }}</th><th class="px-4 py-2.5 text-right">{{ t('orders.dispatched_qty') }}</th><th class="px-4 py-2.5 text-right">{{ t('common.unit_price') }}</th><th class="px-4 py-2.5 text-right">{{ t('common.total') }}</th></tr>
              </thead>
              <tbody class="divide-y divide-line">
                <tr v-for="item in order.items" :key="item.id">
                  <td class="px-4 py-3"><p class="font-medium">{{ item.name }}</p><p class="text-xs text-muted"><span class="font-mono">{{ item.sku }}</span> · {{ item.price_source.label }}</p></td>
                  <td class="px-4 py-3 text-right tabular">{{ formatNumber(item.quantity) }} {{ item.unit }}</td>
                  <td class="px-4 py-3 text-right tabular" :class="item.remaining_quantity ? 'text-amber-700' : 'text-emerald-700'">{{ formatNumber(item.dispatched_quantity) }}</td>
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

        <section class="card">
          <h2 class="border-b border-line px-5 py-3 text-sm font-semibold">{{ t('orders.shipments') }}</h2>
          <p v-if="!order.shipments.length" class="px-5 py-6 text-center text-sm text-muted">{{ t('common.empty') }}</p>
          <ul class="divide-y divide-line">
            <li v-for="s in order.shipments" :key="s.challan_number" class="space-y-2 px-5 py-3">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-sm font-medium">{{ s.challan_number }}</span>
                <StatusBadge :status="s.status" />
                <span class="flex-1 text-xs text-muted">{{ [s.carrier, s.vehicle_number, s.driver_name].filter(Boolean).join(' · ') }}</span>
                <BaseButton size="sm" variant="ghost" icon="FileText" @click="adminApi.challanPdf(s.challan_number, 'en')">EN</BaseButton>
                <BaseButton size="sm" variant="ghost" icon="FileText" @click="adminApi.challanPdf(s.challan_number, 'bn')">বাং</BaseButton>
              </div>
              <p class="text-xs text-muted">{{ s.items.map((l) => `${l.sku} × ${formatNumber(l.quantity)}`).join(', ') }}</p>
              <div v-if="canFulfil" class="flex gap-2">
                <template v-if="s.status.value === 'packing'">
                  <BaseButton size="sm" icon="Truck" :loading="busy === `d-${s.challan_number}`" @click="run(`d-${s.challan_number}`, () => adminApi.dispatchShipment(s.challan_number))">{{ t('orders.dispatch') }}</BaseButton>
                  <BaseButton size="sm" variant="ghost" @click="run(`x-${s.challan_number}`, () => adminApi.cancelShipment(s.challan_number))">{{ t('common.cancel') }}</BaseButton>
                </template>
                <BaseButton v-if="s.status.value === 'dispatched'" size="sm" variant="secondary" icon="CheckCircle2" :loading="busy === `v-${s.challan_number}`" @click="run(`v-${s.challan_number}`, () => adminApi.deliverShipment(s.challan_number))">{{ t('orders.mark_delivered') }}</BaseButton>
              </div>
            </li>
          </ul>
        </section>
      </div>

      <aside class="space-y-6">
        <section v-if="order.invoice" class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('orders.invoice') }}</h2>
          <p class="mt-2 font-mono text-sm">{{ order.invoice.invoice_number }}</p>
          <p class="text-xs text-muted">{{ t('invoices.due_date') }}: {{ formatDate(order.invoice.due_date) }} · {{ t('invoices.balance_due') }}: {{ formatCurrency(order.invoice.balance_due) }}</p>
          <div class="mt-2"><StatusBadge :status="order.invoice.status" /></div>
          <div class="mt-4 flex gap-2">
            <BaseButton size="sm" variant="secondary" icon="FileText" @click="adminApi.invoicePdf(order.invoice.invoice_number, 'en')">{{ t('invoices.pdf_en') }}</BaseButton>
            <BaseButton size="sm" variant="secondary" icon="FileText" @click="adminApi.invoicePdf(order.invoice.invoice_number, 'bn')">{{ t('invoices.pdf_bn') }}</BaseButton>
          </div>
        </section>
        <section class="card p-5 text-sm">
          <h2 class="font-semibold">{{ t('orders.shipping_to') }}</h2>
          <p class="mt-2 font-medium">{{ order.shipping_address.label }}</p>
          <p class="text-muted">{{ order.shipping_address.contact_name }} · {{ order.shipping_address.contact_phone }}</p>
          <p class="text-muted">{{ [order.shipping_address.address_line1, order.shipping_address.area, order.shipping_address.city].filter(Boolean).join(', ') }}</p>
          <p class="mt-3 text-xs text-muted">{{ order.payment_method.label }}<span v-if="order.po_number"> · PO {{ order.po_number }}</span></p>
          <p v-if="order.buyer_notes" class="mt-2 rounded bg-slate-50 p-2 text-xs">{{ order.buyer_notes }}</p>
          <p v-if="order.internal_notes" class="mt-2 rounded bg-amber-50 p-2 text-xs">{{ order.internal_notes }}</p>
        </section>
        <section class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('orders.timeline') }}</h2>
          <ol class="mt-4 space-y-4 border-l border-line pl-4">
            <li v-for="(h, i) in order.history" :key="i" class="relative">
              <span class="absolute top-1.5 -left-[21px] h-2.5 w-2.5 rounded-full bg-brand-600 ring-4 ring-white" />
              <p class="text-sm font-medium">{{ h.to.label }}</p>
              <p class="text-xs text-muted">{{ formatDateTime(h.at) }}<span v-if="h.by"> · {{ h.by }}</span></p>
              <p v-if="h.comment" class="text-xs text-slate-600">{{ h.comment }}</p>
            </li>
          </ol>
        </section>
      </aside>
    </div>

    <BaseModal :open="modal === 'shipment'" :title="t('orders.create_shipment')" size="lg" @update:open="!$event && (modal = null)">
      <div class="space-y-4">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
        <table class="w-full text-sm">
          <thead class="text-left text-xs text-muted"><tr><th class="py-1">{{ t('common.product') }}</th><th class="py-1 text-right">{{ t('orders.remaining_qty') }}</th><th class="w-32 py-1 text-right">{{ t('admin.this_shipment') }}</th></tr></thead>
          <tbody class="divide-y divide-line">
            <tr v-for="i in shippable" :key="i.id">
              <td class="py-2"><p>{{ i.name }}</p><p class="font-mono text-xs text-muted">{{ i.sku }}</p></td>
              <td class="py-2 text-right tabular">{{ formatNumber(i.available) }} {{ i.unit }}</td>
              <td class="py-2"><input v-model.number="shipment.quantities[i.id]" type="number" min="0" :max="i.available" class="input text-right tabular" :aria-label="i.name" /></td>
            </tr>
          </tbody>
        </table>
        <div class="grid gap-3 sm:grid-cols-2">
          <BaseInput v-model="shipment.carrier" :label="t('admin.carrier')" />
          <BaseInput v-model="shipment.vehicle_number" :label="t('admin.vehicle_no')" />
          <BaseInput v-model="shipment.driver_name" :label="t('admin.driver')" />
          <BaseInput v-model="shipment.driver_phone" :label="t('admin.driver_phone')" type="tel" placeholder="01XXXXXXXXX" />
          <BaseInput v-model="shipment.tracking_number" class="sm:col-span-2" :label="t('admin.tracking_no')" />
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="modal = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton icon="Truck" :loading="busy === 'shipment'" :disabled="!Object.values(shipment.quantities).some((q) => Number(q) > 0)" @click="createShipment">{{ t('orders.create_shipment') }}</BaseButton>
      </template>
    </BaseModal>

    <BaseModal :open="modal === 'cash'" :title="t('orders.record_cash')" size="sm" @update:open="!$event && (modal = null)">
      <div class="space-y-4">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
        <BaseInput v-model="cash.amount" :label="t('common.amount')" type="number" min="0.01" step="0.01" />
        <BaseInput v-model="cash.notes" :label="t('common.notes')" />
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="modal = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton :loading="busy === 'cash'" @click="run('cash', () => adminApi.recordCash(order.order_number, { ...cash }))">{{ t('common.confirm') }}</BaseButton>
      </template>
    </BaseModal>

    <ReasonDialog :open="modal === 'cancel'" :title="t('orders.cancel')" :message="t('orders.cancel_confirm')" :loading="busy === 'cancel'" :error="formError" @update:open="!$event && (modal = null)" @confirm="(reason) => run('cancel', () => adminApi.cancelOrder(order.order_number, reason))" />
  </div>
</template>
