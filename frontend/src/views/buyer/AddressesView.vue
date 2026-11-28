<script setup>
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import AddressFields from '@/components/buyer/AddressFields.vue'
import { buyerApi } from '@/api/buyer'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const addresses = ref(null)
const editing = ref(null) // address or 'new'
const deleting = ref(null)
const deleteBusy = ref(false)
const canManage = computed(() => auth.can('company.manage_addresses'))

const blank = { type: 'shipping', label: '', contact_name: '', contact_phone: '', address_line1: '', area: '', city: '', district: '', postal_code: '', is_default: false }
const { form, busy, formError, fieldError, submit, reset } = useApiForm(blank)

const grouped = computed(() => ({
  billing: (addresses.value ?? []).filter((a) => a.type.value === 'billing'),
  shipping: (addresses.value ?? []).filter((a) => a.type.value === 'shipping'),
}))

async function load() {
  addresses.value = await buyerApi.addresses()
}

function open(address = null) {
  editing.value = address ?? 'new'
  reset(address ? { ...blank, ...address, type: address.type.value } : { ...blank })
}

async function save() {
  try {
    await submit(async (data) => {
      const { type, ...rest } = data
      return editing.value === 'new' ? buyerApi.createAddress({ type, ...rest }) : buyerApi.updateAddress(editing.value.id, rest)
    }, { successMessage: t('common.saved') })
    editing.value = null
    load()
  } catch {
    /* rendered */
  }
}

async function confirmDelete() {
  deleteBusy.value = true
  try {
    await buyerApi.deleteAddress(deleting.value.id)
    deleting.value = null
    load()
  } finally {
    deleteBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('addresses.title')">
      <template v-if="canManage" #actions><BaseButton icon="Plus" @click="open()">{{ t('addresses.add') }}</BaseButton></template>
    </PageHeader>
    <SpinnerBlock v-if="!addresses" />
    <div v-else class="space-y-8">
      <section v-for="type in ['shipping', 'billing']" :key="type">
        <h2 class="mb-3 text-sm font-semibold text-muted">{{ t(`addresses.${type}`) }}</h2>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <article v-for="a in grouped[type]" :key="a.id" class="card flex flex-col p-5 text-sm">
            <div class="flex items-start justify-between gap-2">
              <p class="font-semibold">{{ a.label }}</p>
              <span v-if="a.is_default" class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">{{ t('addresses.default') }}</span>
            </div>
            <p class="mt-2 text-muted">{{ a.contact_name }} · {{ a.contact_phone }}</p>
            <p class="text-muted">{{ a.full_address }}</p>
            <div v-if="canManage" class="mt-4 flex gap-2 border-t border-line pt-3">
              <BaseButton size="sm" variant="ghost" @click="open(a)">{{ t('common.edit') }}</BaseButton>
              <BaseButton size="sm" variant="ghost" @click="deleting = a"><AppIcon name="Trash2" :size="14" />{{ t('common.delete') }}</BaseButton>
            </div>
          </article>
        </div>
      </section>
    </div>

    <BaseModal :open="Boolean(editing)" :title="editing === 'new' ? t('addresses.add') : t('common.edit')" size="lg" @update:open="!$event && (editing = null)">
      <form id="address-form" class="space-y-4" novalidate @submit.prevent="save">
        <div v-if="formError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
        <fieldset v-if="editing === 'new'">
          <legend class="label">{{ t('common.type') }}</legend>
          <div class="flex gap-4 text-sm">
            <label class="flex items-center gap-2"><input v-model="form.type" type="radio" value="shipping" class="text-brand-700" />{{ t('addresses.shipping') }}</label>
            <label class="flex items-center gap-2"><input v-model="form.type" type="radio" value="billing" class="text-brand-700" />{{ t('addresses.billing') }}</label>
          </div>
        </fieldset>
        <AddressFields v-model="form" :error-for="fieldError" />
        <label class="flex items-center gap-2 text-sm"><input v-model="form.is_default" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('addresses.make_default') }}</label>
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="editing = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" form="address-form" :loading="busy">{{ t('common.save') }}</BaseButton>
      </template>
    </BaseModal>

    <ConfirmDialog :open="Boolean(deleting)" :title="t('common.delete')" :message="deleting ? `${deleting.label} — ${deleting.full_address}` : ''" danger :loading="deleteBusy" @update:open="!$event && (deleting = null)" @confirm="confirmDelete" />
  </div>
</template>
