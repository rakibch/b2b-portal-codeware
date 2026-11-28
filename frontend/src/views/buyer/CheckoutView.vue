<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import { buyerApi } from '@/api/buyer'
import { useApiForm } from '@/composables/useApiForm'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const { t } = useI18n()
const { formatCurrency, formatNumber } = useFormat()
const router = useRouter()
const cart = useCartStore()
const toast = useToastStore()

const preview = ref(null)
const addresses = ref([])
const { form, busy, formError, fieldError, submit } = useApiForm({ shipping_address_id: null, payment_method: null, po_number: '', buyer_notes: '' })

const methods = computed(() => preview.value?.payment_methods ?? [])
const canPlace = computed(() => preview.value && !preview.value.has_errors && !preview.value.below_minimum && form.shipping_address_id && methods.value.find((m) => m.value === form.payment_method)?.available)

async function load() {
  const [p, a] = await Promise.all([buyerApi.checkoutPreview(), buyerApi.addresses('shipping')])
  if (!p.item_count) return router.replace({ name: 'buyer.cart' })
  preview.value = p
  addresses.value = a
  form.shipping_address_id = a.find((x) => x.is_default)?.id ?? a[0]?.id ?? null
  form.payment_method = p.payment_methods.find((m) => m.available)?.value ?? null
}

async function placeOrder() {
  try {
    const result = await submit((data) => buyerApi.checkout(data))
    cart.reset()
    toast.success(result.message)
    const order = result.data
    if (order.payment_method.value === 'gateway') {
      const pay = await buyerApi.payOnline(order.order_number)
      window.location.assign(pay.redirect_url)
      return
    }
    router.replace({ name: 'buyer.order', params: { number: order.order_number }, query: { placed: '1' } })
  } catch (e) {
    if (e?.context?.lines) await load()
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('checkout.title')" />
    <SpinnerBlock v-if="!preview" />
    <form v-else class="grid gap-6 lg:grid-cols-3" novalidate @submit.prevent="placeOrder">
      <div class="space-y-6 lg:col-span-2">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ formError }}</div>

        <section class="card p-5">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold">{{ t('checkout.delivery_address') }}</h2>
            <RouterLink :to="{ name: 'buyer.addresses' }" class="text-xs font-medium text-brand-700 hover:underline">{{ t('addresses.add') }}</RouterLink>
          </div>
          <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label v-for="a in addresses" :key="a.id" class="flex cursor-pointer gap-3 rounded-lg border p-4 text-sm" :class="form.shipping_address_id === a.id ? 'border-brand-600 bg-brand-50/50 ring-1 ring-brand-600' : 'border-line hover:bg-slate-50'">
              <input v-model="form.shipping_address_id" type="radio" name="address" :value="a.id" class="mt-1 text-brand-700" />
              <span class="min-w-0">
                <span class="block font-semibold">{{ a.label }}</span>
                <span class="block text-muted">{{ a.contact_name }} · {{ a.contact_phone }}</span>
                <span class="block text-muted">{{ a.full_address }}</span>
              </span>
            </label>
          </div>
          <p v-if="fieldError('shipping_address_id')" class="field-error">{{ fieldError('shipping_address_id') }}</p>
        </section>

        <section class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('checkout.payment_method') }}</h2>
          <div class="mt-4 space-y-3">
            <label v-for="m in methods" :key="m.value" class="flex gap-3 rounded-lg border p-4 text-sm" :class="[!m.available ? 'cursor-not-allowed opacity-60' : 'cursor-pointer', form.payment_method === m.value ? 'border-brand-600 bg-brand-50/50 ring-1 ring-brand-600' : 'border-line']">
              <input v-model="form.payment_method" type="radio" name="method" :value="m.value" :disabled="!m.available" class="mt-1 text-brand-700" />
              <span class="min-w-0 flex-1">
                <span class="block font-semibold">{{ m.value === 'credit' ? t('checkout.credit', { days: formatNumber(preview.credit.payment_terms_days) }) : m.label }}</span>
                <span v-if="m.value === 'credit' && preview.credit.enabled" class="block text-xs text-muted">{{ t('checkout.credit_available', { amount: formatCurrency(preview.credit.available) }) }}</span>
                <span v-if="!m.available && m.reason" class="block text-xs text-red-600">{{ m.reason }}</span>
              </span>
            </label>
          </div>
        </section>

        <section class="card grid gap-4 p-5 sm:grid-cols-2">
          <BaseInput v-model="form.po_number" :label="t('checkout.po_number')" maxlength="64" :error="fieldError('po_number')" />
          <div class="sm:col-span-2">
            <label for="notes" class="label">{{ t('checkout.notes') }}</label>
            <textarea id="notes" v-model="form.buyer_notes" rows="3" maxlength="2000" class="input" />
          </div>
        </section>
      </div>

      <aside class="card h-fit space-y-4 p-5">
        <h2 class="text-sm font-semibold">{{ t('checkout.order_summary') }}</h2>
        <dl class="space-y-2 text-sm">
          <div class="flex justify-between"><dt class="text-muted">{{ t('cart.subtotal') }}</dt><dd class="tabular">{{ formatCurrency(preview.totals.subtotal) }}</dd></div>
          <div v-if="Number(preview.totals.discount_total) > 0" class="flex justify-between text-emerald-700"><dt>{{ t('cart.discount') }}</dt><dd class="tabular">− {{ formatCurrency(preview.totals.discount_total) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">{{ t('cart.vat') }}</dt><dd class="tabular">{{ formatCurrency(preview.totals.vat_total) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">{{ t('cart.shipping') }}</dt><dd class="tabular">{{ formatCurrency(preview.totals.shipping_total) }}</dd></div>
          <div class="flex justify-between border-t border-line pt-3 text-base font-semibold"><dt>{{ t('cart.grand_total') }}</dt><dd class="tabular">{{ formatCurrency(preview.totals.grand_total) }}</dd></div>
        </dl>
        <p v-if="preview.below_minimum" class="rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700">{{ t('checkout.below_minimum', { amount: formatCurrency(preview.minimum_order_amount) }) }}</p>
        <p v-if="preview.requires_approval" class="flex gap-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
          <AppIcon name="ShieldCheck" :size="14" class="mt-0.5 shrink-0" />{{ t('checkout.approval_notice', { amount: formatCurrency(preview.approval_limit) }) }}
        </p>
        <BaseButton type="submit" block size="lg" :loading="busy" :disabled="!canPlace">{{ busy ? t('checkout.placing') : t('checkout.place_order') }}</BaseButton>
        <RouterLink :to="{ name: 'buyer.cart' }" class="block text-center text-xs text-muted hover:text-ink">{{ t('common.back') }}</RouterLink>
      </aside>
    </form>
  </div>
</template>
