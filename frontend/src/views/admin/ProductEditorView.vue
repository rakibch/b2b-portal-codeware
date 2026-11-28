<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import FilePicker from '@/components/shared/FilePicker.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ id: { type: [String, Number], default: null } })
const { t } = useI18n()
const { formatNumber } = useFormat()
const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const loading = ref(true)
const saving = ref(false)
const errors = ref({})
const formError = ref(null)
const lookups = reactive({ categories: [], brands: [], units: [], groups: [] })
const product = ref(null)
const image = ref(null)
const stockModal = ref(false)
const stock = reactive({ delta: '', reason: '' })
const stockError = ref(null)
const confirmDelete = ref(false)

const canEdit = computed(() => auth.can('catalog.manage'))
const canPrice = computed(() => auth.can('pricing.manage') || auth.can('catalog.manage'))

const form = reactive({
  sku: '', barcode: '', category_id: null, brand_id: null, unit_id: null,
  name: { en: '', bn: '' }, description: { en: '', bn: '' }, pack_label: { en: '', bn: '' },
  pack_size: 1, base_price: '', vat_percent: '0', moq: 1, step_quantity: 1, max_order_quantity: '',
  stock_quantity: 0, low_stock_threshold: 0, is_active: true, is_featured: false,
})
const tiers = ref([])

const err = (k) => errors.value[k]?.[0] ?? null
const options = (list, label = (x) => x.name) => list.map((x) => ({ value: x.id, label: label(x) }))

async function load() {
  const [categories, brands, units, groups] = await Promise.all([
    adminApi.categories(), adminApi.brands(), adminApi.units(),
    auth.can('pricing.manage') ? adminApi.customerGroups() : Promise.resolve([]),
  ])
  Object.assign(lookups, { categories, brands, units, groups })
  if (props.id) {
    const p = await adminApi.product(props.id)
    product.value = p
    Object.assign(form, {
      sku: p.sku, barcode: p.barcode ?? '', category_id: p.category?.id ?? null, brand_id: p.brand?.id ?? null,
      unit_id: units.find((u) => u.code === p.unit?.code)?.id ?? null,
      name: { en: p.translations.name.en ?? '', bn: p.translations.name.bn ?? '' },
      description: { en: p.translations.description?.en ?? '', bn: p.translations.description?.bn ?? '' },
      pack_label: { en: p.translations.pack_label?.en ?? '', bn: p.translations.pack_label?.bn ?? '' },
      pack_size: p.pack_size, base_price: p.list_price, vat_percent: p.vat_percent, moq: p.moq, step_quantity: p.step_quantity,
      max_order_quantity: p.max_order_quantity ?? '', low_stock_threshold: p.low_stock_threshold, is_active: p.is_active, is_featured: p.is_featured,
    })
    tiers.value = (p.tiers ?? []).map((tier) => ({ ...tier, max_quantity: tier.max_quantity ?? '' }))
  }
  loading.value = false
}

function buildFormData() {
  const fd = new FormData()
  const put = (k, v) => fd.append(k, v === null || v === undefined ? '' : v)
  for (const k of ['sku', 'barcode', 'category_id', 'brand_id', 'unit_id', 'pack_size', 'base_price', 'vat_percent', 'moq', 'step_quantity', 'max_order_quantity', 'low_stock_threshold']) put(k, form[k])
  for (const k of ['name', 'description', 'pack_label']) for (const l of ['en', 'bn']) put(`${k}[${l}]`, form[k][l])
  put('is_active', form.is_active ? '1' : '0')
  put('is_featured', form.is_featured ? '1' : '0')
  if (!props.id) put('stock_quantity', form.stock_quantity)
  if (image.value) fd.append('image', image.value)
  tiers.value.forEach((tier, i) => {
    put(`tiers[${i}][min_quantity]`, tier.min_quantity)
    put(`tiers[${i}][max_quantity]`, tier.max_quantity)
    put(`tiers[${i}][unit_price]`, tier.unit_price)
    put(`tiers[${i}][customer_group_id]`, tier.customer_group_id ?? '')
  })
  if (!tiers.value.length && props.id) fd.append('tiers', '')
  return fd
}

async function save() {
  saving.value = true
  errors.value = {}
  formError.value = null
  try {
    const saved = await adminApi.saveProduct(props.id, buildFormData())
    toast.success(t('common.saved'))
    if (!props.id) router.replace({ name: 'admin.product-edit', params: { id: saved.id } })
    else {
      image.value = null
      await load()
    }
  } catch (e) {
    if (e.isValidation) errors.value = e.errors
    formError.value = e.message
  } finally {
    saving.value = false
  }
}

async function adjustStock() {
  stockError.value = null
  try {
    const r = await adminApi.adjustStock(props.id, { delta: Number(stock.delta), reason: stock.reason })
    toast.success(r.message)
    stockModal.value = false
    Object.assign(stock, { delta: '', reason: '' })
    await load()
  } catch (e) {
    stockError.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
  }
}

async function remove() {
  await adminApi.deleteProduct(props.id)
  router.replace({ name: 'admin.products' })
}

function addTier() {
  const last = tiers.value.filter((x) => !x.customer_group_id).at(-1)
  const min = last ? (Number(last.max_quantity) || Number(last.min_quantity)) + 1 : Number(form.moq) || 1
  tiers.value.push({ customer_group_id: null, min_quantity: min, max_quantity: '', unit_price: '' })
}

onMounted(load)
</script>

<template>
  <SpinnerBlock v-if="loading" />
  <div v-else>
    <button type="button" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink" @click="router.push({ name: 'admin.products' })"><AppIcon name="ChevronLeft" :size="16" />{{ t('nav.products') }}</button>
    <PageHeader :title="id ? t('admin.edit_product') : t('admin.new_product')" :subtitle="product?.name">
      <template v-if="id" #actions>
        <BaseButton v-if="auth.can('inventory.adjust')" variant="secondary" icon="Boxes" @click="stockModal = true">{{ t('admin.adjust_stock') }} ({{ formatNumber(product?.available_quantity ?? 0) }})</BaseButton>
        <BaseButton v-if="canEdit" variant="ghost" icon="Trash2" @click="confirmDelete = true">{{ t('common.delete') }}</BaseButton>
      </template>
    </PageHeader>

    <form class="space-y-6" novalidate @submit.prevent="save">
      <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ formError }}</div>
      <fieldset :disabled="!canEdit" class="space-y-6">
        <section class="card grid gap-4 p-5 md:grid-cols-2">
          <h2 class="text-sm font-semibold md:col-span-2">{{ t('admin.translations') }}</h2>
          <BaseInput v-model="form.name.en" :label="t('admin.name_en')" required :error="err('name.en')" />
          <BaseInput v-model="form.name.bn" :label="t('admin.name_bn')" class="font-bangla" lang="bn" :error="err('name.bn')" />
          <div><label class="label" for="den">{{ t('admin.description_en') }}</label><textarea id="den" v-model="form.description.en" rows="3" class="input" /></div>
          <div><label class="label" for="dbn">{{ t('admin.description_bn') }}</label><textarea id="dbn" v-model="form.description.bn" rows="3" class="input font-bangla" lang="bn" /></div>
        </section>

        <section class="card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
          <BaseInput v-model="form.sku" :label="t('common.sku')" required class="font-mono" :error="err('sku')" />
          <BaseInput v-model="form.barcode" :label="t('catalog.barcode')" :error="err('barcode')" />
          <BaseSelect v-model="form.category_id" :label="t('catalog.category')" :options="options(lookups.categories)" :placeholder="t('common.none')" required :error="err('category_id')" />
          <BaseSelect v-model="form.brand_id" :label="t('catalog.brand')" :options="[{ value: null, label: '—' }, ...options(lookups.brands)]" :error="err('brand_id')" />
          <BaseSelect v-model="form.unit_id" :label="t('catalog.unit')" :options="options(lookups.units, (u) => `${u.name} (${u.code})`)" :placeholder="t('common.none')" required :error="err('unit_id')" />
          <BaseInput v-model="form.pack_size" :label="t('catalog.pack_size')" type="number" min="1" :error="err('pack_size')" />
          <BaseInput v-model="form.pack_label.en" :label="`${t('admin.pack_label')} (EN)`" placeholder="Carton" />
          <BaseInput v-model="form.pack_label.bn" :label="`${t('admin.pack_label')} (বাং)`" placeholder="কার্টন" />
        </section>

        <section class="card grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-4">
          <BaseInput v-model="form.base_price" :label="t('catalog.list_price')" type="number" min="0.01" step="0.01" required :error="err('base_price')" />
          <BaseInput v-model="form.vat_percent" :label="`${t('catalog.vat')} %`" type="number" min="0" max="100" step="0.01" :error="err('vat_percent')" />
          <BaseInput v-model="form.moq" :label="t('catalog.moq')" type="number" min="1" required :error="err('moq')" />
          <BaseInput v-model="form.step_quantity" :label="t('admin.step')" type="number" min="1" required :error="err('step_quantity')" />
          <BaseInput v-model="form.max_order_quantity" :label="t('admin.max_order_qty')" type="number" min="1" :error="err('max_order_quantity')" />
          <BaseInput v-if="!id" v-model="form.stock_quantity" :label="t('admin.opening_stock')" type="number" min="0" :error="err('stock_quantity')" />
          <BaseInput v-model="form.low_stock_threshold" :label="t('admin.low_stock_threshold')" type="number" min="0" :error="err('low_stock_threshold')" />
          <div class="flex flex-col justify-end gap-2 text-sm">
            <label class="flex items-center gap-2"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('status.active') }}</label>
            <label class="flex items-center gap-2"><input v-model="form.is_featured" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('catalog.featured_only') }}</label>
          </div>
          <div class="sm:col-span-2 lg:col-span-4">
            <div class="flex items-center gap-4">
              <img v-if="product?.image_url" :src="product.image_url" alt="" class="h-16 w-16 rounded-lg border border-line object-cover" />
              <FilePicker v-model="image" class="flex-1" :label="t('admin.image')" accept=".jpg,.jpeg,.png,.webp" :max-mb="3" :error="err('image')" />
            </div>
          </div>
        </section>
      </fieldset>

      <section class="card p-5">
        <div class="mb-3 flex items-center justify-between">
          <div><h2 class="text-sm font-semibold">{{ t('admin.tiers') }}</h2><p class="text-xs text-muted">{{ t('admin.tiers_hint') }}</p></div>
          <BaseButton v-if="canPrice" size="sm" variant="secondary" icon="Plus" @click="addTier">{{ t('admin.add_tier') }}</BaseButton>
        </div>
        <p v-if="errors.tiers || Object.keys(errors).some((k) => k.startsWith('tiers'))" class="field-error mb-2">{{ Object.entries(errors).find(([k]) => k.startsWith('tiers'))?.[1]?.[0] }}</p>
        <div class="overflow-x-auto">
          <table v-if="tiers.length" class="w-full min-w-[560px] text-sm">
            <thead class="text-left text-xs text-muted"><tr><th class="py-1 pr-2">{{ t('admin.group') }}</th><th class="py-1 pr-2">{{ t('admin.min_qty') }}</th><th class="py-1 pr-2">{{ t('admin.max_qty') }}</th><th class="py-1 pr-2">{{ t('common.unit_price') }}</th><th /></tr></thead>
            <tbody>
              <tr v-for="(tier, i) in tiers" :key="i">
                <td class="py-1 pr-2">
                  <select v-model="tier.customer_group_id" class="input" :disabled="!canPrice" :aria-label="t('admin.group')">
                    <option :value="null">{{ t('admin.general') }}</option>
                    <option v-for="g in lookups.groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                  </select>
                </td>
                <td class="py-1 pr-2"><input v-model="tier.min_quantity" type="number" min="1" class="input tabular" :disabled="!canPrice" :aria-label="t('admin.min_qty')" /></td>
                <td class="py-1 pr-2"><input v-model="tier.max_quantity" type="number" min="1" class="input tabular" :placeholder="'∞'" :disabled="!canPrice" :aria-label="t('admin.max_qty')" /></td>
                <td class="py-1 pr-2"><input v-model="tier.unit_price" type="number" min="0.01" step="0.01" class="input tabular" :disabled="!canPrice" :aria-label="t('common.unit_price')" /></td>
                <td class="py-1"><button v-if="canPrice" type="button" class="rounded p-2 text-slate-400 hover:text-red-600" :aria-label="t('common.remove')" @click="tiers.splice(i, 1)"><AppIcon name="Trash2" :size="16" /></button></td>
              </tr>
            </tbody>
          </table>
          <p v-else class="py-4 text-center text-sm text-muted">{{ t('common.empty') }}</p>
        </div>
      </section>

      <div v-if="canEdit" class="flex justify-end gap-2">
        <BaseButton variant="secondary" @click="router.push({ name: 'admin.products' })">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" :loading="saving">{{ t('common.save_changes') }}</BaseButton>
      </div>
    </form>

    <BaseModal v-model:open="stockModal" :title="t('admin.adjust_stock')" size="sm">
      <div class="space-y-4">
        <div v-if="stockError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ stockError }}</div>
        <p class="text-sm text-muted">{{ t('admin.current_stock', { qty: formatNumber(product?.available_quantity ?? 0) }) }}</p>
        <BaseInput v-model="stock.delta" :label="t('admin.stock_delta')" type="number" :hint="t('admin.stock_delta_hint')" />
        <BaseInput v-model="stock.reason" :label="t('common.reason')" maxlength="500" />
      </div>
      <template #footer>
        <BaseButton variant="secondary" @click="stockModal = false">{{ t('common.cancel') }}</BaseButton>
        <BaseButton :disabled="!Number(stock.delta) || stock.reason.length < 3" @click="adjustStock">{{ t('common.save') }}</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog v-model:open="confirmDelete" :title="t('common.delete')" :message="product?.name" danger @confirm="remove" />
  </div>
</template>
