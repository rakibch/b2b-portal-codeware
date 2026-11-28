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
import SkuLookup from '@/components/buyer/SkuLookup.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ id: { type: String, required: true } })
const { t, locale } = useI18n()
const { formatCurrency, formatDate, formatNumber } = useFormat()
const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const company = ref(null)
const users = ref([])
const lookups = ref({ customer_groups: [], account_managers: [] })
const statement = ref(null)
const prices = ref([])
const tab = ref('ledger')
const busy = ref(null)
const formError = ref(null)

const canTerms = computed(() => auth.can('credit.manage'))
const canAdjust = computed(() => auth.can('ledger.adjust'))
const canPricing = computed(() => auth.can('pricing.manage'))
const canOrder = computed(() => auth.can('orders.create_on_behalf') && company.value?.kyc.status.value === 'approved')

const terms = reactive({ credit_limit: '0', payment_terms_days: 0, credit_enabled: false, credit_on_hold: false, customer_group_id: null, account_manager_id: null })
const modal = ref(null) // terms | adjust | credit_note | price
const adjust = reactive({ direction: 'credit', amount: '', note: '' })
const note = reactive({ amount: '', reason: '', invoice_number: '' })
const price = reactive({ id: null, product: null, sku: '', unit_price: '', min_quantity: 1, valid_from: '', valid_until: '' })

async function load() {
  const [c, u, l] = await Promise.all([adminApi.company(props.id), adminApi.companyUsers(props.id), adminApi.lookups().catch(() => lookups.value)])
  company.value = c
  users.value = u
  lookups.value = l
  if (c.credit) Object.assign(terms, { credit_limit: c.credit.credit_limit, payment_terms_days: c.credit.payment_terms_days, credit_enabled: c.credit.credit_enabled, credit_on_hold: c.credit.credit_on_hold })
  Object.assign(terms, { customer_group_id: c.customer_group?.id ?? null, account_manager_id: c.account_manager?.id ?? null })
  await Promise.all([loadLedger(), canPricing.value ? loadPrices() : null])
}

async function loadLedger() {
  if (!auth.can('ledger.view')) return
  statement.value = await adminApi.companyLedger(props.id, {})
}

async function loadPrices() {
  prices.value = (await adminApi.contractPrices(props.id)).data
}

async function submit(key, fn, success) {
  busy.value = key
  formError.value = null
  try {
    const result = await fn()
    toast.success(result?.message ?? success ?? t('common.saved'))
    modal.value = null
    await load()
  } catch (e) {
    formError.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
  } finally {
    busy.value = null
  }
}

function openModal(name, payload = null) {
  formError.value = null
  if (name === 'adjust') Object.assign(adjust, { direction: 'credit', amount: '', note: '' })
  if (name === 'credit_note') Object.assign(note, { amount: '', reason: '', invoice_number: '' })
  if (name === 'price') {
    Object.assign(price, payload
      ? { id: payload.id, product: payload.product, sku: payload.product.sku, unit_price: payload.unit_price, min_quantity: payload.min_quantity, valid_from: payload.valid_from ?? '', valid_until: payload.valid_until ?? '' }
      : { id: null, product: null, sku: '', unit_price: '', min_quantity: 1, valid_from: '', valid_until: '' })
  }
  modal.value = name
}

function savePrice() {
  const payload = { unit_price: price.unit_price, min_quantity: Number(price.min_quantity) || 1, valid_from: price.valid_from || null, valid_until: price.valid_until || null }
  return submit('price', () => (price.id ? adminApi.updateContractPrice(props.id, price.id, payload) : adminApi.createContractPrice(props.id, { ...payload, product_id: price.product?.id })))
}

async function deletePrice(p) {
  await adminApi.deleteContractPrice(props.id, p.id)
  loadPrices()
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <SpinnerBlock v-if="!company" />
  <div v-else>
    <button type="button" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink" @click="router.back()"><AppIcon name="ChevronLeft" :size="16" />{{ t('nav.companies') }}</button>
    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">{{ company.name }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
          <StatusBadge :status="company.kyc.status" />
          <span>{{ company.business_type.label }}</span>
          <span v-if="company.customer_group">· {{ company.customer_group.name }}</span>
          <span v-if="company.account_manager">· {{ company.account_manager.name }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <BaseButton v-if="canOrder" icon="Plus" :to="{ name: 'admin.order-new', params: { id: company.id } }">{{ t('orders.on_behalf') }}</BaseButton>
        <BaseButton v-if="canTerms" variant="secondary" icon="Landmark" @click="openModal('terms')">{{ t('kyc.credit_terms') }}</BaseButton>
        <BaseButton variant="ghost" :to="{ name: 'admin.kyc-review', params: { id: company.id } }">{{ t('kyc.documents') }}</BaseButton>
      </div>
    </div>

    <div v-if="company.credit" class="mb-6 grid gap-4 sm:grid-cols-4">
      <div class="card p-4"><p class="text-xs text-muted">{{ t('credit.limit') }}</p><p class="mt-1 text-lg font-semibold tabular">{{ formatCurrency(company.credit.credit_limit) }}</p></div>
      <div class="card p-4"><p class="text-xs text-muted">{{ t('credit.outstanding') }}</p><p class="mt-1 text-lg font-semibold tabular">{{ formatCurrency(company.credit.outstanding_balance) }}</p></div>
      <div class="card p-4"><p class="text-xs text-muted">{{ t('credit.available') }}</p><p class="mt-1 text-lg font-semibold tabular text-emerald-700">{{ formatCurrency(company.credit.available_credit) }}</p></div>
      <div class="card p-4"><p class="text-xs text-muted">{{ t('credit.terms') }}</p><p class="mt-1 text-lg font-semibold">{{ t('credit.net_days', { days: formatNumber(company.credit.payment_terms_days) }) }}</p><p v-if="company.credit.credit_on_hold" class="text-xs font-semibold text-red-600">{{ t('credit.on_hold') }}</p></div>
    </div>

    <div class="mb-4 flex gap-2 border-b border-line" role="tablist">
      <button v-for="tb in ['ledger', 'prices', 'users']" v-show="tb !== 'prices' || canPricing" :key="tb" type="button" role="tab" :aria-selected="tab === tb" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium" :class="tab === tb ? 'border-brand-700 text-brand-800' : 'border-transparent text-muted hover:text-ink'" @click="tab = tb">
        {{ { ledger: t('ledger.title'), prices: t('admin.contract_prices'), users: t('team.title') }[tb] }}
      </button>
    </div>

    <section v-if="tab === 'ledger'" class="card">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-3">
        <p class="text-sm text-muted" v-if="statement">{{ t('ledger.closing_balance') }}: <span class="font-semibold text-ink tabular">{{ formatCurrency(statement.closing_balance) }}</span></p>
        <div class="flex gap-2">
          <BaseButton size="sm" variant="ghost" icon="FileText" @click="adminApi.exportCompanyLedger(company.id, {})">{{ t('ledger.export_xlsx') }}</BaseButton>
          <template v-if="canAdjust">
            <BaseButton size="sm" variant="secondary" @click="openModal('credit_note')">{{ t('ledger.credit_note') }}</BaseButton>
            <BaseButton size="sm" variant="secondary" @click="openModal('adjust')">{{ t('admin.ledger_adjust') }}</BaseButton>
          </template>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table v-if="statement?.entries.length" class="w-full min-w-[680px] text-sm">
          <thead class="border-b border-line bg-slate-50 text-left text-xs text-muted"><tr><th class="px-4 py-2">{{ t('common.date') }}</th><th class="px-4 py-2">{{ t('ledger.description') }}</th><th class="px-4 py-2 text-right">{{ t('ledger.debit') }}</th><th class="px-4 py-2 text-right">{{ t('ledger.credit') }}</th><th class="px-4 py-2 text-right">{{ t('ledger.balance') }}</th></tr></thead>
          <tbody class="divide-y divide-line">
            <tr v-for="e in statement.entries" :key="e.id">
              <td class="px-4 py-2 whitespace-nowrap">{{ formatDate(e.date) }}</td>
              <td class="px-4 py-2"><span class="text-xs text-muted">{{ e.type.label }}</span><br />{{ e.description }}</td>
              <td class="px-4 py-2 text-right tabular">{{ Number(e.debit) ? formatCurrency(e.debit) : '' }}</td>
              <td class="px-4 py-2 text-right tabular text-emerald-700">{{ Number(e.credit) ? formatCurrency(e.credit) : '' }}</td>
              <td class="px-4 py-2 text-right font-medium tabular">{{ formatCurrency(e.balance) }}</td>
            </tr>
          </tbody>
        </table>
        <p v-else class="px-5 py-8 text-center text-sm text-muted">{{ t('ledger.no_entries') }}</p>
      </div>
    </section>

    <section v-if="tab === 'prices' && canPricing" class="card">
      <div class="flex justify-end border-b border-line px-5 py-3"><BaseButton size="sm" icon="Plus" @click="openModal('price')">{{ t('common.add') }}</BaseButton></div>
      <ul class="divide-y divide-line">
        <li v-for="p in prices" :key="p.id" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
          <div class="min-w-0 flex-1"><p class="font-medium">{{ p.product.name }}</p><p class="font-mono text-xs text-muted">{{ p.product.sku }} · {{ t('catalog.list_price') }} {{ formatCurrency(p.product.base_price) }}</p></div>
          <span class="font-semibold tabular text-brand-700">{{ formatCurrency(p.unit_price) }}</span>
          <span class="text-xs text-muted">{{ t('admin.min_qty') }} {{ formatNumber(p.min_quantity) }}</span>
          <span class="text-xs text-muted">{{ formatDate(p.valid_from) }} – {{ formatDate(p.valid_until) }}</span>
          <BaseButton size="sm" variant="ghost" @click="openModal('price', p)">{{ t('common.edit') }}</BaseButton>
          <BaseButton size="sm" variant="ghost" @click="deletePrice(p)"><AppIcon name="Trash2" :size="14" /></BaseButton>
        </li>
        <li v-if="!prices.length" class="px-5 py-8 text-center text-sm text-muted">{{ t('common.empty') }}</li>
      </ul>
    </section>

    <section v-if="tab === 'users'" class="card divide-y divide-line">
      <div v-for="u in users" :key="u.id" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
        <div class="min-w-0 flex-1"><p class="font-medium">{{ u.name }}</p><p class="text-xs text-muted">{{ u.email }} · {{ u.phone }}</p></div>
        <span class="text-xs">{{ t(`roles.${u.roles?.[0]}`) }}</span>
        <StatusBadge :status="u.is_active ? 'active' : 'inactive'" :label="u.is_active ? t('status.active') : t('status.inactive')" />
      </div>
    </section>

    <!-- Credit terms -->
    <BaseModal :open="modal === 'terms'" :title="t('kyc.credit_terms')" @update:open="!$event && (modal = null)">
      <div class="space-y-4">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
        <BaseInput v-model="terms.credit_limit" :label="t('credit.limit')" type="number" min="0" step="1000" />
        <BaseInput v-model="terms.payment_terms_days" :label="t('kyc.terms_days')" type="number" min="0" max="365" />
        <label class="flex items-center gap-2 text-sm"><input v-model="terms.credit_enabled" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('kyc.credit_enabled') }}</label>
        <label class="flex items-center gap-2 text-sm"><input v-model="terms.credit_on_hold" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('credit.on_hold') }}</label>
        <div>
          <label for="t-group" class="label">{{ t('kyc.customer_group') }}</label>
          <select id="t-group" v-model="terms.customer_group_id" class="input"><option :value="null">{{ t('common.none') }}</option><option v-for="g in lookups.customer_groups" :key="g.id" :value="g.id">{{ g.name }}</option></select>
        </div>
        <div>
          <label for="t-manager" class="label">{{ t('kyc.assign_manager') }}</label>
          <select id="t-manager" v-model="terms.account_manager_id" class="input"><option :value="null">{{ t('common.none') }}</option><option v-for="m in lookups.account_managers" :key="m.id" :value="m.id">{{ m.name }}</option></select>
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="modal = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton :loading="busy === 'terms'" @click="submit('terms', () => adminApi.updateTerms(company.id, { ...terms, payment_terms_days: Number(terms.payment_terms_days) }))">{{ t('common.save') }}</BaseButton>
      </template>
    </BaseModal>

    <!-- Ledger adjustment -->
    <BaseModal :open="modal === 'adjust'" :title="t('admin.ledger_adjust')" size="sm" @update:open="!$event && (modal = null)">
      <div class="space-y-4">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
        <fieldset class="flex gap-4 text-sm">
          <label class="flex items-center gap-2"><input v-model="adjust.direction" type="radio" value="credit" class="text-brand-700" />{{ t('ledger.credit') }} <span class="text-xs text-muted">({{ t('admin.reduces_balance') }})</span></label>
          <label class="flex items-center gap-2"><input v-model="adjust.direction" type="radio" value="debit" class="text-brand-700" />{{ t('ledger.debit') }}</label>
        </fieldset>
        <BaseInput v-model="adjust.amount" :label="t('common.amount')" type="number" min="0.01" step="0.01" />
        <BaseInput v-model="adjust.note" :label="t('common.notes')" maxlength="500" />
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="modal = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton :loading="busy === 'adjust'" :disabled="!adjust.amount || adjust.note.length < 5" @click="submit('adjust', () => adminApi.adjustLedger(company.id, { ...adjust }))">{{ t('admin.ledger_adjust') }}</BaseButton>
      </template>
    </BaseModal>

    <!-- Credit note -->
    <BaseModal :open="modal === 'credit_note'" :title="t('ledger.credit_note')" size="sm" @update:open="!$event && (modal = null)">
      <div class="space-y-4">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
        <BaseInput v-model="note.invoice_number" :label="`${t('invoices.invoice_number')} (${t('common.optional')})`" placeholder="INV-2026-000001" />
        <BaseInput v-model="note.amount" :label="t('common.amount')" type="number" min="0.01" step="0.01" />
        <BaseInput v-model="note.reason" :label="t('common.reason')" maxlength="1000" />
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="modal = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton :loading="busy === 'credit_note'" :disabled="!note.amount || note.reason.length < 5" @click="submit('credit_note', () => adminApi.creditNote(company.id, { ...note, invoice_number: note.invoice_number || null }))">{{ t('ledger.credit_note') }}</BaseButton>
      </template>
    </BaseModal>

    <!-- Contract price -->
    <BaseModal :open="modal === 'price'" :title="t('admin.contract_prices')" @update:open="!$event && (modal = null)">
      <div class="space-y-4">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
        <div v-if="!price.id">
          <label class="label">{{ t('common.product') }}</label>
          <SkuLookup v-model="price.sku" @select="price.product = $event" />
          <p v-if="price.product" class="mt-1 text-xs text-muted">{{ price.product.name }}</p>
        </div>
        <p v-else class="text-sm font-medium">{{ price.product.name }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <BaseInput v-model="price.unit_price" :label="t('common.unit_price')" type="number" min="0.01" step="0.01" />
          <BaseInput v-model="price.min_quantity" :label="t('admin.min_qty')" type="number" min="1" />
          <BaseInput v-model="price.valid_from" :label="t('common.from')" type="date" />
          <BaseInput v-model="price.valid_until" :label="t('common.to')" type="date" />
        </div>
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="modal = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton :loading="busy === 'price'" :disabled="!price.unit_price || (!price.id && !price.product)" @click="savePrice">{{ t('common.save') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
