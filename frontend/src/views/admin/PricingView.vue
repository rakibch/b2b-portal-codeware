<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
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
const groups = ref(null)
const editing = ref(null)
const error = ref(null)
const saving = ref(false)
const form = reactive({ code: '', name_en: '', name_bn: '', description_en: '', description_bn: '', discount_percent: '0', priority: 0, is_active: true })

async function load() {
  groups.value = await adminApi.customerGroups()
}

function open(group = null) {
  error.value = null
  Object.assign(form, {
    code: group?.code ?? '', name_en: group?.translations?.name?.en ?? '', name_bn: group?.translations?.name?.bn ?? '',
    description_en: group?.translations?.description?.en ?? '', description_bn: group?.translations?.description?.bn ?? '',
    discount_percent: group?.discount_percent ?? '0', priority: group?.priority ?? 0, is_active: group?.is_active ?? true,
  })
  editing.value = group ?? { id: null }
}

async function save() {
  saving.value = true
  error.value = null
  try {
    await adminApi.saveCustomerGroup(editing.value.id, {
      code: form.code,
      name: { en: form.name_en, bn: form.name_bn || null },
      description: { en: form.description_en || null, bn: form.description_bn || null },
      discount_percent: form.discount_percent || 0,
      priority: Number(form.priority) || 0,
      is_active: form.is_active,
    })
    toast.success(t('common.saved'))
    editing.value = null
    await load()
  } catch (e) {
    error.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
  } finally {
    saving.value = false
  }
}

async function remove(group) {
  try {
    await adminApi.deleteCustomerGroup(group.id)
    await load()
  } catch (e) {
    toast.error(e.message)
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('nav.pricing')" :subtitle="t('admin.pricing_hint')">
      <template #actions><BaseButton icon="Plus" @click="open()">{{ t('common.add') }}</BaseButton></template>
    </PageHeader>
    <SpinnerBlock v-if="!groups" />
    <div v-else class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <article v-for="g in groups" :key="g.id" class="card flex flex-col p-5">
        <div class="flex items-start justify-between gap-2">
          <div><p class="font-semibold">{{ g.name }}</p><p class="font-mono text-xs text-muted">{{ g.code }}</p></div>
          <StatusBadge :status="g.is_active ? 'active' : 'inactive'" :label="g.is_active ? t('status.active') : t('status.inactive')" />
        </div>
        <p v-if="g.description" class="mt-2 text-sm text-muted">{{ g.description }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
          <div><dt class="text-xs text-muted">{{ t('admin.group_discount') }}</dt><dd class="font-semibold tabular">{{ formatNumber(g.discount_percent, 2) }}%</dd></div>
          <div><dt class="text-xs text-muted">{{ t('nav.companies') }}</dt><dd class="font-semibold tabular">{{ formatNumber(g.companies_count ?? 0) }}</dd></div>
        </dl>
        <div class="mt-4 flex gap-2 border-t border-line pt-3">
          <BaseButton size="sm" variant="ghost" @click="open(g)">{{ t('common.edit') }}</BaseButton>
          <BaseButton v-if="!g.companies_count" size="sm" variant="ghost" @click="remove(g)">{{ t('common.delete') }}</BaseButton>
        </div>
      </article>
    </div>

    <BaseModal :open="Boolean(editing)" :title="editing?.id ? t('common.edit') : t('common.add')" @update:open="!$event && (editing = null)">
      <form id="group-form" class="grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
        <div v-if="error" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 sm:col-span-2" role="alert">{{ error }}</div>
        <BaseInput v-model="form.code" :label="t('admin.code')" required class="font-mono" />
        <BaseInput v-model="form.discount_percent" :label="t('admin.group_discount')" type="number" min="0" max="100" step="0.01" />
        <BaseInput v-model="form.name_en" :label="t('admin.name_en')" required />
        <BaseInput v-model="form.name_bn" :label="t('admin.name_bn')" class="font-bangla" lang="bn" />
        <BaseInput v-model="form.description_en" :label="t('admin.description_en')" />
        <BaseInput v-model="form.description_bn" :label="t('admin.description_bn')" class="font-bangla" lang="bn" />
        <BaseInput v-model="form.priority" :label="t('admin.sort_order')" type="number" min="0" />
        <label class="flex items-center gap-2 self-end text-sm"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('status.active') }}</label>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="editing = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" form="group-form" :loading="saving">{{ t('common.save') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
