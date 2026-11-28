<script setup>
import { onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { buyerApi } from '@/api/buyer'
import { useFormat } from '@/composables/useFormat'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ reference: { type: String, required: true } })

const { t, locale } = useI18n()
const { formatCurrency, formatDate, formatNumber } = useFormat()
const router = useRouter()
const toast = useToastStore()
const rfq = ref(null)
const busy = ref(null)
const orderModal = ref(false)
const addresses = ref([])
const order = ref({ shipping_address_id: null, payment_method: 'credit', po_number: '' })
const orderError = ref(null)

async function load() {
  rfq.value = await buyerApi.rfq(props.reference)
}

async function act(key, fn) {
  busy.value = key
  try {
    rfq.value = await fn(props.reference)
  } catch (e) {
    toast.error(e.message)
  } finally {
    busy.value = null
  }
}

async function openOrder() {
  addresses.value = await buyerApi.addresses('shipping')
  order.value.shipping_address_id = addresses.value.find((a) => a.is_default)?.id ?? addresses.value[0]?.id ?? null
  orderError.value = null
  orderModal.value = true
}

async function placeOrder() {
  busy.value = 'order'
  orderError.value = null
  try {
    const result = await buyerApi.orderFromRfq(props.reference, order.value)
    toast.success(result.message)
    router.push({ name: 'buyer.order', params: { number: result.data.order_number }, query: { placed: '1' } })
  } catch (e) {
    orderError.value = e.message
  } finally {
    busy.value = null
  }
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <SpinnerBlock v-if="!rfq" />
  <div v-else>
    <RouterLink :to="{ name: 'buyer.rfqs' }" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink"><AppIcon name="ChevronLeft" :size="16" />{{ t('rfq.title') }}</RouterLink>
    <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="font-mono text-2xl font-semibold">{{ rfq.reference }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
          <StatusBadge :status="rfq.status" />
          <span>{{ formatDate(rfq.created_at) }}</span>
          <span v-if="rfq.valid_until" :class="rfq.is_expired ? 'font-semibold text-red-600' : ''">· {{ t('rfq.valid_until') }}: {{ formatDate(rfq.valid_until) }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <template v-if="rfq.can_accept">
          <BaseButton icon="ShoppingCart" @click="openOrder">{{ t('rfq.accept_and_order') }}</BaseButton>
          <BaseButton variant="secondary" icon="Check" :loading="busy === 'accept'" @click="act('accept', buyerApi.acceptRfq)">{{ t('rfq.accept') }}</BaseButton>
          <BaseButton variant="ghost" :loading="busy === 'reject'" @click="act('reject', buyerApi.rejectRfq)">{{ t('rfq.reject') }}</BaseButton>
        </template>
        <BaseButton v-else-if="rfq.status.value === 'accepted'" icon="ShoppingCart" @click="openOrder">{{ t('checkout.place_order') }}</BaseButton>
        <BaseButton v-if="rfq.can_cancel" variant="ghost" icon="XCircle" :loading="busy === 'cancel'" @click="act('cancel', buyerApi.cancelRfq)">{{ t('rfq.cancel') }}</BaseButton>
      </div>
    </div>

    <section class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[560px] text-sm">
          <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted">
            <tr>
              <th class="px-4 py-2.5">{{ t('common.product') }}</th>
              <th class="px-4 py-2.5 text-right">{{ t('common.qty') }}</th>
              <th class="px-4 py-2.5 text-right">{{ t('catalog.list_price') }}</th>
              <th class="px-4 py-2.5 text-right">{{ t('rfq.target_price') }}</th>
              <th class="px-4 py-2.5 text-right">{{ t('rfq.quoted_price') }}</th>
              <th class="px-4 py-2.5 text-right">{{ t('common.total') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="item in rfq.items" :key="item.id">
              <td class="px-4 py-3"><p class="font-medium">{{ item.product?.name }}</p><p class="font-mono text-xs text-muted">{{ item.product?.sku }}</p><p v-if="item.notes" class="text-xs text-muted">{{ item.notes }}</p></td>
              <td class="px-4 py-3 text-right tabular">{{ formatNumber(item.quantity) }} {{ item.product?.unit }}</td>
              <td class="px-4 py-3 text-right tabular text-muted">{{ formatCurrency(item.product?.list_price) }}</td>
              <td class="px-4 py-3 text-right tabular">{{ item.target_unit_price ? formatCurrency(item.target_unit_price) : '—' }}</td>
              <td class="px-4 py-3 text-right font-semibold tabular text-brand-700">{{ item.quoted_unit_price ? formatCurrency(item.quoted_unit_price) : '—' }}</td>
              <td class="px-4 py-3 text-right tabular">{{ item.quoted_line_total ? formatCurrency(item.quoted_line_total) : '—' }}</td>
            </tr>
          </tbody>
          <tfoot v-if="rfq.quoted_total" class="border-t border-line bg-slate-50">
            <tr><td colspan="5" class="px-4 py-3 text-right font-semibold">{{ t('rfq.quoted_total') }} <span class="text-xs font-normal text-muted">({{ t('rfq.excl_vat') }})</span></td><td class="px-4 py-3 text-right font-semibold tabular">{{ formatCurrency(rfq.quoted_total) }}</td></tr>
          </tfoot>
        </table>
      </div>
    </section>

    <div class="mt-6 grid gap-6 md:grid-cols-2">
      <section v-if="rfq.buyer_notes" class="card p-5"><h2 class="text-sm font-semibold">{{ t('common.notes') }}</h2><p class="mt-2 text-sm whitespace-pre-line text-slate-600">{{ rfq.buyer_notes }}</p></section>
      <section v-if="rfq.quote_notes" class="card p-5"><h2 class="text-sm font-semibold">{{ t('rfq.quote_notes') }}</h2><p class="mt-2 text-sm whitespace-pre-line text-slate-600">{{ rfq.quote_notes }}</p></section>
    </div>

    <BaseModal v-model:open="orderModal" :title="t('rfq.accept_and_order')">
      <div class="space-y-4">
        <div v-if="orderError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ orderError }}</div>
        <div>
          <label class="label" for="rfq-address">{{ t('checkout.delivery_address') }}</label>
          <select id="rfq-address" v-model="order.shipping_address_id" class="input">
            <option v-for="a in addresses" :key="a.id" :value="a.id">{{ a.label }} — {{ a.full_address }}</option>
          </select>
        </div>
        <div>
          <label class="label" for="rfq-method">{{ t('checkout.payment_method') }}</label>
          <select id="rfq-method" v-model="order.payment_method" class="input">
            <option value="credit">{{ t('checkout.credit_short') }}</option>
            <option value="cod">{{ t('checkout.cod') }}</option>
            <option value="bank_transfer">{{ t('checkout.bank_transfer') }}</option>
          </select>
        </div>
        <div>
          <label class="label" for="rfq-po">{{ t('checkout.po_number') }}</label>
          <input id="rfq-po" v-model="order.po_number" class="input" maxlength="64" />
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="orderModal = false">{{ t('common.cancel') }}</BaseButton>
        <BaseButton :loading="busy === 'order'" :disabled="!order.shipping_address_id" @click="placeOrder">{{ t('checkout.place_order') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
