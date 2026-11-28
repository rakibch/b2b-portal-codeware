<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import SkuLookup from '@/components/buyer/SkuLookup.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ id: { type: String, required: true } })
const { t } = useI18n()
const { formatNumber } = useFormat()
const router = useRouter()
const toast = useToastStore()

const company = ref(null)
const users = ref([])
let uid = 0
const lines = ref([{ key: ++uid, sku: '', product: null, quantity: null }])
const form = ref({ buyer_user_id: null, shipping_address_id: null, payment_method: 'credit', po_number: '', buyer_notes: '', internal_notes: '' })
const busy = ref(false)
const error = ref(null)
const lineErrors = ref([])

const shippingAddresses = computed(() => (company.value?.addresses ?? []).filter((a) => a.type.value === 'shipping'))

onMounted(async () => {
  const [c, u] = await Promise.all([adminApi.company(props.id), adminApi.companyUsers(props.id)])
  company.value = c
  users.value = u.filter((x) => x.is_active)
  form.value.buyer_user_id = users.value[0]?.id ?? null
  form.value.shipping_address_id = shippingAddresses.value.find((a) => a.is_default)?.id ?? shippingAddresses.value[0]?.id ?? null
})

async function submit() {
  busy.value = true
  error.value = null
  lineErrors.value = []
  try {
    const items = lines.value.filter((l) => l.product && l.quantity).map((l) => ({ product_id: l.product.id, quantity: Number(l.quantity) }))
    const result = await adminApi.placeOrderFor(props.id, { ...form.value, items })
    toast.success(result.message)
    router.replace({ name: 'admin.order', params: { number: result.data.order_number } })
  } catch (e) {
    error.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
    lineErrors.value = e.context?.lines ?? []
  } finally {
    busy.value = false
  }
}

function lineError(line) {
  return lineErrors.value.find((l) => l.product_id === line.product?.id)?.errors?.[0]?.message
}
</script>

<template>
  <SpinnerBlock v-if="!company" />
  <div v-else>
    <PageHeader :title="t('orders.on_behalf')" :subtitle="company.name" />
    <form class="space-y-6" novalidate @submit.prevent="submit">
      <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ error }}</div>
      <section class="card grid gap-4 p-5 sm:grid-cols-2">
        <div>
          <label class="label" for="buyer">{{ t('orders.placed_by') }}</label>
          <select id="buyer" v-model="form.buyer_user_id" class="input"><option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} · {{ u.email }}</option></select>
        </div>
        <div>
          <label class="label" for="addr">{{ t('checkout.delivery_address') }}</label>
          <select id="addr" v-model="form.shipping_address_id" class="input"><option v-for="a in shippingAddresses" :key="a.id" :value="a.id">{{ a.label }} — {{ a.city }}</option></select>
        </div>
        <div>
          <label class="label" for="method">{{ t('checkout.payment_method') }}</label>
          <select id="method" v-model="form.payment_method" class="input">
            <option value="credit">{{ t('checkout.credit_short') }}</option>
            <option value="cod">{{ t('checkout.cod') }}</option>
            <option value="bank_transfer">{{ t('checkout.bank_transfer') }}</option>
          </select>
        </div>
        <div><label class="label" for="po">{{ t('checkout.po_number') }}</label><input id="po" v-model="form.po_number" class="input" maxlength="64" /></div>
        <div class="sm:col-span-2"><label class="label" for="internal">{{ t('admin.internal_notes') }}</label><textarea id="internal" v-model="form.internal_notes" rows="2" class="input" maxlength="2000" /></div>
      </section>

      <section class="card divide-y divide-line">
        <div v-for="(line, i) in lines" :key="line.key" class="grid gap-3 p-4 sm:grid-cols-12 sm:items-start">
          <div class="sm:col-span-7">
            <label class="label">{{ t('common.product') }} {{ formatNumber(i + 1) }}</label>
            <SkuLookup v-model="line.sku" :invalid="Boolean(lineError(line))" @select="(p) => { line.product = p; line.quantity = line.quantity || p.moq }" />
            <p v-if="line.product" class="mt-1 text-xs text-muted">{{ line.product.name }} · {{ t('catalog.moq_full', { moq: formatNumber(line.product.moq), unit: line.product.unit }) }}</p>
            <p v-if="lineError(line)" class="field-error">{{ lineError(line) }}</p>
          </div>
          <div class="sm:col-span-4"><label class="label">{{ t('common.quantity') }}</label><input v-model.number="line.quantity" type="number" min="1" class="input tabular" /></div>
          <div class="flex sm:col-span-1 sm:justify-end sm:pt-7"><button type="button" class="rounded p-2 text-slate-400 hover:text-red-600" :aria-label="t('common.remove')" @click="lines = lines.length > 1 ? lines.filter((l) => l.key !== line.key) : lines"><AppIcon name="Trash2" :size="16" /></button></div>
        </div>
        <div class="p-4"><BaseButton size="sm" variant="secondary" icon="Plus" @click="lines.push({ key: ++uid, sku: '', product: null, quantity: null })">{{ t('common.add') }}</BaseButton></div>
      </section>

      <div class="flex justify-end gap-2">
        <BaseButton variant="secondary" @click="router.back()">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" :loading="busy" :disabled="!form.buyer_user_id || !form.shipping_address_id || !lines.some((l) => l.product && l.quantity)">{{ t('checkout.place_order') }}</BaseButton>
      </div>
    </form>
  </div>
</template>
