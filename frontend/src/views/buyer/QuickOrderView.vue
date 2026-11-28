<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SkuLookup from '@/components/buyer/SkuLookup.vue'
import { buyerApi } from '@/api/buyer'
import { debounce } from '@/composables/useDebounce'
import { useFormat } from '@/composables/useFormat'
import { useCartStore } from '@/stores/cart'
import { useToastStore } from '@/stores/toast'

const { t } = useI18n()
const { formatCurrency, formatNumber } = useFormat()
const cart = useCartStore()
const toast = useToastStore()
const router = useRouter()

let uid = 0
const blankRow = () => ({ key: ++uid, sku: '', quantity: null })
const rows = ref(Array.from({ length: 5 }, blankRow))
const results = reactive({ byRow: {}, unmatched: {} })
const validating = ref(false)
const adding = ref(false)
const importing = ref(false)
const importPreview = ref(null)
let controller = null

const filledRows = computed(() => rows.value.filter((r) => r.sku.trim() || r.quantity))

/** Server-side validation of the whole pad: matching, MOQ/step/stock and live pricing. */
const validate = debounce(async () => {
  const payload = filledRows.value.map((r) => ({ row: r.key, sku: r.sku.trim(), quantity: r.quantity }))
  if (!payload.length) {
    results.byRow = {}
    results.unmatched = {}
    return
  }
  controller?.abort()
  controller = new AbortController()
  validating.value = true
  try {
    const data = await buyerApi.validateRows(payload, controller.signal)
    const byRow = {}
    for (const m of data.matched) for (const row of m.rows) byRow[row] = m
    results.byRow = byRow
    results.unmatched = Object.fromEntries(data.unmatched.map((u) => [u.row, u]))
  } catch {
    /* aborted */
  } finally {
    validating.value = false
  }
}, 350)

watch(rows, validate, { deep: true })

function rowState(row) {
  if (results.unmatched[row.key]) return { error: results.unmatched[row.key].message }
  const match = results.byRow[row.key]
  if (!match) return null
  return { match, error: match.pricing.errors?.[0]?.message ?? null, suggested: match.pricing.errors?.[0]?.meta?.suggested_quantity }
}

const readyCount = computed(() => filledRows.value.filter((r) => { const s = rowState(r); return s && s.match && !s.error }).length)
const problemCount = computed(() => filledRows.value.filter((r) => rowState(r)?.error).length)
const padTotal = computed(() => {
  const unique = new Map(Object.values(results.byRow).map((m) => [m.product.id, m]))
  return [...unique.values()].reduce((sum, m) => sum + (m.pricing.is_valid ? Number(m.pricing.line_total) : 0), 0)
})

function addRows(count = 1) {
  rows.value.push(...Array.from({ length: count }, blankRow))
}

function removeRow(key) {
  rows.value = rows.value.filter((r) => r.key !== key)
  if (!rows.value.length) addRows(1)
}

function onSelect(row, product) {
  row.sku = product.sku
  if (!row.quantity) row.quantity = product.moq
}

function clearPad() {
  rows.value = Array.from({ length: 5 }, blankRow)
  importPreview.value = null
}

async function addAll() {
  adding.value = true
  try {
    const payload = filledRows.value.map((r) => ({ row: r.key, sku: r.sku.trim(), quantity: r.quantity }))
    cart.apply(await buyerApi.addRowsToCart(payload))
    toast.success(t('cart.updated'))
    router.push({ name: 'buyer.cart' })
  } catch (e) {
    toast.error(e.message)
  } finally {
    adding.value = false
  }
}

async function onFile(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  importing.value = true
  try {
    importPreview.value = await buyerApi.importFile(file)
  } catch (e) {
    toast.error(e.message)
  } finally {
    importing.value = false
  }
}

function loadPreviewIntoPad() {
  const imported = importPreview.value.matched.map((m) => ({ key: ++uid, sku: m.product.sku, quantity: m.quantity }))
  const unmatched = importPreview.value.unmatched.map((u) => ({ key: ++uid, sku: u.sku ?? '', quantity: Number(u.quantity) || null }))
  rows.value = [...imported, ...unmatched, blankRow()]
  importPreview.value = null
}
</script>

<template>
  <div>
    <PageHeader :title="t('quick_order.title')" :subtitle="t('quick_order.subtitle')">
      <template #actions>
        <BaseButton variant="secondary" icon="Trash2" @click="clearPad">{{ t('quick_order.clear_pad') }}</BaseButton>
      </template>
    </PageHeader>

    <div class="grid gap-6 xl:grid-cols-3">
      <section class="card overflow-visible xl:col-span-2">
        <div class="overflow-x-auto">
          <table class="w-full min-w-[640px] text-sm">
            <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted">
              <tr>
                <th class="w-10 px-3 py-2">#</th>
                <th class="px-3 py-2">{{ t('quick_order.sku_or_name') }}</th>
                <th class="w-28 px-3 py-2">{{ t('common.qty') }}</th>
                <th class="px-3 py-2">{{ t('common.product') }}</th>
                <th class="w-32 px-3 py-2 text-right">{{ t('catalog.line_total') }}</th>
                <th class="w-10" />
              </tr>
            </thead>
            <tbody class="divide-y divide-line">
              <tr v-for="(row, index) in rows" :key="row.key" class="align-top">
                <td class="px-3 py-2 pt-4 text-xs text-muted tabular">{{ formatNumber(index + 1) }}</td>
                <td class="px-3 py-2"><SkuLookup v-model="row.sku" :invalid="Boolean(rowState(row)?.error && !rowState(row)?.match)" @select="onSelect(row, $event)" /></td>
                <td class="px-3 py-2"><input v-model.number="row.quantity" type="number" min="1" inputmode="numeric" class="input tabular" :class="rowState(row)?.error ? 'border-red-400' : ''" :aria-label="t('common.qty')" /></td>
                <td class="px-3 py-2 pt-3">
                  <template v-if="rowState(row)?.match">
                    <p class="font-medium text-ink">{{ rowState(row).match.product.name }}</p>
                    <p class="text-xs text-muted tabular">{{ formatCurrency(rowState(row).match.pricing.unit_price) }} / {{ rowState(row).match.product.unit }}</p>
                  </template>
                  <p v-if="rowState(row)?.error" class="mt-1 flex flex-wrap items-center gap-2 text-xs text-red-600">
                    {{ rowState(row).error }}
                    <button v-if="rowState(row).suggested" type="button" class="font-semibold text-brand-700 underline" @click="row.quantity = rowState(row).suggested">{{ t('quick_order.use_suggested', { qty: formatNumber(rowState(row).suggested) }) }}</button>
                  </p>
                </td>
                <td class="px-3 py-2 pt-3 text-right font-medium tabular">{{ rowState(row)?.match && !rowState(row)?.error ? formatCurrency(rowState(row).match.pricing.line_total) : '—' }}</td>
                <td class="px-2 py-2 pt-3"><button type="button" class="rounded p-1 text-slate-400 hover:text-red-600" :aria-label="t('common.remove')" @click="removeRow(row.key)"><AppIcon name="X" :size="16" /></button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex flex-col gap-3 border-t border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="flex gap-2">
            <BaseButton size="sm" variant="secondary" icon="Plus" @click="addRows(1)">{{ t('quick_order.add_row') }}</BaseButton>
            <BaseButton size="sm" variant="ghost" @click="addRows(5)">{{ t('quick_order.add_rows') }}</BaseButton>
          </div>
          <div class="flex flex-wrap items-center gap-3 text-xs">
            <span v-if="validating" class="text-muted">{{ t('quick_order.validating') }}</span>
            <span class="font-medium text-emerald-700">{{ t('quick_order.valid_rows', { count: formatNumber(readyCount) }) }}</span>
            <span v-if="problemCount" class="font-medium text-red-600">{{ t('quick_order.invalid_rows', { count: formatNumber(problemCount) }) }}</span>
            <span class="text-sm font-semibold tabular text-ink">{{ formatCurrency(padTotal) }}</span>
            <BaseButton icon="ShoppingCart" :loading="adding" :disabled="!readyCount || problemCount > 0 || validating" @click="addAll">{{ t('quick_order.add_all') }}</BaseButton>
          </div>
        </div>
      </section>

      <section class="card h-fit p-5">
        <h2 class="text-sm font-semibold">{{ t('quick_order.upload_title') }}</h2>
        <p class="mt-1 text-xs text-muted">{{ t('quick_order.upload_hint') }}</p>
        <div class="mt-4 flex flex-col gap-2">
          <label class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
            <AppIcon :name="importing ? 'Loader2' : 'Upload'" :size="16" :class="importing ? 'animate-spin' : ''" />
            {{ t('quick_order.choose_file') }}
            <input type="file" class="sr-only" accept=".csv,.xlsx,.xls" @change="onFile" />
          </label>
          <BaseButton variant="link" icon="FileText" @click="buyerApi.downloadTemplate()">{{ t('quick_order.download_template') }}</BaseButton>
        </div>

        <div v-if="importPreview" class="mt-5 space-y-3">
          <p class="text-xs font-semibold text-emerald-700">{{ t('quick_order.matched') }}: {{ formatNumber(importPreview.summary.matched) }}</p>
          <ul class="max-h-48 space-y-1 overflow-y-auto text-xs">
            <li v-for="m in importPreview.matched" :key="m.product.id" class="flex justify-between gap-2">
              <span class="truncate"><span class="font-mono">{{ m.product.sku }}</span> × {{ formatNumber(m.quantity) }}</span>
              <span :class="m.pricing.is_valid ? 'text-emerald-700' : 'text-red-600'">{{ m.pricing.is_valid ? formatCurrency(m.pricing.line_total) : m.pricing.errors[0].message }}</span>
            </li>
          </ul>
          <template v-if="importPreview.unmatched.length">
            <p class="text-xs font-semibold text-red-600">{{ t('quick_order.unmatched') }}: {{ formatNumber(importPreview.unmatched.length) }}</p>
            <ul class="max-h-32 space-y-1 overflow-y-auto text-xs text-red-600">
              <li v-for="u in importPreview.unmatched" :key="u.row">{{ t('quick_order.row') }} {{ formatNumber(u.row) }}: {{ u.sku || '—' }} — {{ u.message }}</li>
            </ul>
          </template>
          <BaseButton block @click="loadPreviewIntoPad">{{ t('quick_order.load_into_pad') }}</BaseButton>
        </div>
      </section>
    </div>
  </div>
</template>
