<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkuLookup from '@/components/buyer/SkuLookup.vue'
import { buyerApi } from '@/api/buyer'
import { catalogApi } from '@/api/catalog'
import { useFormat } from '@/composables/useFormat'
import { useToastStore } from '@/stores/toast'

const { t } = useI18n()
const { formatNumber } = useFormat()
const route = useRoute()
const router = useRouter()
const toast = useToastStore()

let uid = 0
const lines = ref([])
const notes = ref('')
const busy = ref(false)
const lineErrors = ref({})
const formError = ref(null)

function addLine(product = null, quantity = null) {
  lines.value.push({ key: ++uid, sku: product?.sku ?? '', product, quantity: quantity ?? product?.moq ?? null, target_unit_price: null, notes: '' })
}

function onSelect(line, product) {
  line.product = product
  line.sku = product.sku
  if (!line.quantity) line.quantity = product.moq
}

async function submit() {
  busy.value = true
  formError.value = null
  lineErrors.value = {}
  const valid = lines.value.filter((l) => l.product && l.quantity)
  try {
    const result = await buyerApi.createRfq({
      buyer_notes: notes.value || null,
      items: valid.map((l) => ({ product_id: l.product.id, quantity: Number(l.quantity), target_unit_price: l.target_unit_price || null, notes: l.notes || null })),
    })
    toast.success(result.message)
    router.replace({ name: 'buyer.rfq', params: { reference: result.data.reference } })
  } catch (e) {
    formError.value = e.message
    for (const line of e?.context?.lines ?? []) {
      lineErrors.value[valid[line.index]?.key] = line.errors[0]?.message
    }
  } finally {
    busy.value = false
  }
}

onMounted(async () => {
  const productId = Number(route.query.product)
  const sku = typeof route.query.sku === 'string' ? route.query.sku : null
  if (productId && sku) {
    const matches = await catalogApi.lookup(sku).catch(() => [])
    const product = matches.find((p) => p.id === productId)
    if (product) addLine(product, Number(route.query.qty) || product.moq)
  }
  if (!lines.value.length) addLine()
})
</script>

<template>
  <div>
    <PageHeader :title="t('rfq.new')" :subtitle="t('rfq.new_subtitle')" />
    <form class="space-y-6" novalidate @submit.prevent="submit">
      <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ formError }}</div>

      <section class="card divide-y divide-line">
        <div v-for="(line, index) in lines" :key="line.key" class="grid gap-3 p-4 md:grid-cols-12 md:items-start">
          <div class="md:col-span-4">
            <label class="label">{{ t('common.product') }} {{ formatNumber(index + 1) }}</label>
            <SkuLookup v-model="line.sku" :invalid="Boolean(lineErrors[line.key])" @select="onSelect(line, $event)" />
            <p v-if="line.product" class="mt-1 text-xs text-muted">{{ line.product.name }} · {{ t('catalog.moq_full', { moq: formatNumber(line.product.moq), unit: line.product.unit }) }}</p>
            <p v-if="lineErrors[line.key]" class="field-error">{{ lineErrors[line.key] }}</p>
          </div>
          <div class="md:col-span-2">
            <label class="label">{{ t('common.quantity') }}</label>
            <input v-model.number="line.quantity" type="number" min="1" class="input tabular" />
          </div>
          <div class="md:col-span-2">
            <label class="label">{{ t('rfq.target_price') }} <span class="text-xs font-normal text-muted">({{ t('common.optional') }})</span></label>
            <input v-model="line.target_unit_price" type="number" min="0" step="0.01" class="input tabular" />
          </div>
          <div class="md:col-span-3">
            <label class="label">{{ t('common.notes') }}</label>
            <input v-model="line.notes" type="text" maxlength="500" class="input" />
          </div>
          <div class="flex md:col-span-1 md:justify-end md:pt-7">
            <button type="button" class="rounded p-2 text-slate-400 hover:text-red-600" :aria-label="t('common.remove')" :disabled="lines.length === 1" @click="lines = lines.filter((l) => l.key !== line.key)"><AppIcon name="Trash2" :size="16" /></button>
          </div>
        </div>
        <div class="p-4"><BaseButton size="sm" variant="secondary" icon="Plus" @click="addLine()">{{ t('common.add') }}</BaseButton></div>
      </section>

      <section class="card p-5">
        <label for="rfq-notes" class="label">{{ t('common.notes') }}</label>
        <textarea id="rfq-notes" v-model="notes" rows="3" maxlength="2000" class="input" :placeholder="t('rfq.notes_placeholder')" />
      </section>

      <div class="flex justify-end gap-2">
        <BaseButton variant="secondary" :to="{ name: 'buyer.rfqs' }">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" icon="MessageSquareQuote" :loading="busy" :disabled="!lines.some((l) => l.product && l.quantity)">{{ t('rfq.submit') }}</BaseButton>
      </div>
    </form>
  </div>
</template>
