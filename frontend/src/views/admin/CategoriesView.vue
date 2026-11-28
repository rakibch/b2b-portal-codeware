<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useToastStore } from '@/stores/toast'

const { t } = useI18n()
const { formatNumber } = useFormat()
const toast = useToastStore()
const tab = ref('categories')
const lists = reactive({ categories: null, brands: null, units: null })
const editing = ref(null) // { kind, id }
const form = reactive({})
const error = ref(null)
const saving = ref(false)

async function load() {
  const [c, b, u] = await Promise.all([adminApi.categories(), adminApi.brands(), adminApi.units()])
  Object.assign(lists, { categories: c, brands: b, units: u })
}

function open(kind, item = null) {
  error.value = null
  Object.keys(form).forEach((k) => delete form[k])
  if (kind === 'categories') Object.assign(form, { name_en: item?.translations?.name?.en ?? '', name_bn: item?.translations?.name?.bn ?? '', parent_id: item?.parent_id ?? null, sort_order: item?.sort_order ?? 0, is_active: item?.is_active ?? true })
  if (kind === 'brands') Object.assign(form, { name: item?.name ?? '', is_active: item?.is_active ?? true })
  if (kind === 'units') Object.assign(form, { code: item?.code ?? '', name_en: item?.translations?.name?.en ?? '', name_bn: item?.translations?.name?.bn ?? '', short_en: item?.translations?.short_name?.en ?? '', short_bn: item?.translations?.short_name?.bn ?? '', is_active: item?.is_active ?? true })
  editing.value = { kind, id: item?.id ?? null }
}

function payload(kind) {
  if (kind === 'categories') return { name: { en: form.name_en, bn: form.name_bn || null }, parent_id: form.parent_id, sort_order: Number(form.sort_order) || 0, is_active: form.is_active }
  if (kind === 'brands') return { name: form.name, is_active: form.is_active }
  return { code: form.code, name: { en: form.name_en, bn: form.name_bn || null }, short_name: { en: form.short_en, bn: form.short_bn || null }, is_active: form.is_active }
}

async function save() {
  saving.value = true
  error.value = null
  const { kind, id } = editing.value
  const fn = { categories: adminApi.saveCategory, brands: adminApi.saveBrand, units: adminApi.saveUnit }[kind]
  try {
    await fn(id, payload(kind))
    toast.success(t('common.saved'))
    editing.value = null
    await load()
  } catch (e) {
    error.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
  } finally {
    saving.value = false
  }
}

async function remove(kind, item) {
  try {
    if (kind === 'categories') await adminApi.deleteCategory(item.id)
    else await adminApi.deleteBrand(item.id)
    await load()
  } catch (e) {
    toast.error(e.message)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('nav.categories')">
      <template #actions><BaseButton icon="Plus" @click="open(tab)">{{ t('common.add') }}</BaseButton></template>
    </PageHeader>
    <div class="mb-4 flex gap-2 border-b border-line" role="tablist">
      <button v-for="tb in ['categories', 'brands', 'units']" :key="tb" type="button" role="tab" :aria-selected="tab === tb" class="-mb-px border-b-2 px-3 py-2 text-sm font-medium" :class="tab === tb ? 'border-brand-700 text-brand-800' : 'border-transparent text-muted hover:text-ink'" @click="tab = tb">
        {{ { categories: t('nav.categories'), brands: t('nav.brands'), units: t('catalog.unit') }[tb] }}
      </button>
    </div>
    <SpinnerBlock v-if="!lists[tab]" />
    <section v-else class="card divide-y divide-line">
      <div v-for="item in lists[tab]" :key="item.id" class="flex flex-wrap items-center gap-3 px-5 py-3 text-sm">
        <div class="min-w-0 flex-1">
          <p class="font-medium">{{ item.name }} <span v-if="item.code" class="font-mono text-xs text-muted">({{ item.code }})</span></p>
          <p v-if="item.translations?.name?.bn" class="font-bangla text-xs text-muted" lang="bn">{{ item.translations.name.bn }}</p>
        </div>
        <span v-if="item.products_count !== undefined" class="text-xs text-muted">{{ t('catalog.products_found', { count: formatNumber(item.products_count) }) }}</span>
        <StatusBadge :status="item.is_active ? 'active' : 'inactive'" :label="item.is_active ? t('status.active') : t('status.inactive')" />
        <BaseButton size="sm" variant="ghost" @click="open(tab, item)">{{ t('common.edit') }}</BaseButton>
        <BaseButton v-if="tab !== 'units'" size="sm" variant="ghost" :aria-label="t('common.delete')" @click="remove(tab, item)"><AppIcon name="Trash2" :size="14" /></BaseButton>
      </div>
    </section>

    <BaseModal :open="Boolean(editing)" :title="editing?.id ? t('common.edit') : t('common.add')" @update:open="!$event && (editing = null)">
      <form v-if="editing" id="cat-form" class="space-y-4" novalidate @submit.prevent="save">
        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ error }}</div>
        <template v-if="editing.kind === 'brands'">
          <BaseInput v-model="form.name" :label="t('catalog.brand')" required />
        </template>
        <template v-else>
          <BaseInput v-if="editing.kind === 'units'" v-model="form.code" :label="t('admin.code')" required class="font-mono" />
          <BaseInput v-model="form.name_en" :label="t('admin.name_en')" required />
          <BaseInput v-model="form.name_bn" :label="t('admin.name_bn')" class="font-bangla" lang="bn" />
          <template v-if="editing.kind === 'units'">
            <BaseInput v-model="form.short_en" :label="`${t('admin.short_name')} (EN)`" required />
            <BaseInput v-model="form.short_bn" :label="`${t('admin.short_name')} (বাং)`" />
          </template>
          <template v-if="editing.kind === 'categories'">
            <div>
              <label class="label" for="parent">{{ t('admin.parent_category') }}</label>
              <select id="parent" v-model="form.parent_id" class="input">
                <option :value="null">—</option>
                <option v-for="c in lists.categories.filter((c) => c.id !== editing.id)" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <BaseInput v-model="form.sort_order" :label="t('admin.sort_order')" type="number" min="0" />
          </template>
        </template>
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('status.active') }}</label>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="editing = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" form="cat-form" :loading="saving">{{ t('common.save') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
