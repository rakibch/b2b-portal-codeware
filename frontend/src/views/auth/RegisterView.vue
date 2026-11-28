<script setup>
import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import AddressFields from '@/components/buyer/AddressFields.vue'
import FilePicker from '@/components/shared/FilePicker.vue'
import { authApi } from '@/api/auth'
import { ApiError } from '@/api/ApiError'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const { t, locale } = useI18n()
const router = useRouter()
const auth = useAuthStore()
const localeStore = useLocaleStore()

const PHONE = /^(?:\+?88)?01[3-9]\d{8}$/
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
const BUSINESS_TYPES = ['retailer', 'wholesaler', 'distributor', 'super_shop', 'pharmacy', 'restaurant', 'corporate', 'other']
const emptyAddress = () => ({ label: '', contact_name: '', contact_phone: '', address_line1: '', area: '', city: '', district: '', postal_code: '' })

const steps = ['step_company', 'step_contact', 'step_addresses', 'step_documents', 'step_review']
const step = ref(0)
const busy = ref(false)
const formError = ref(null)
const errors = ref({})
const sameAsBilling = ref(true)

const data = reactive({
  company: { name: '', trade_name: '', business_type: null, contact_person: '', email: '', phone: '', trade_license_no: '', tin_no: '', bin_no: '', website: '' },
  user: { name: '', email: '', phone: '', designation: '', password: '', password_confirmation: '' },
  billing_address: emptyAddress(),
  delivery_addresses: [emptyAddress()],
  documents: { trade_license: null, nid: null, tin_certificate: null, bin_certificate: null },
  accept_terms: false,
})

const businessTypes = computed(() => BUSINESS_TYPES.map((v) => ({ value: v, label: t(`business_type.${v}`) })))
const err = (key) => {
  const value = errors.value[key]
  return Array.isArray(value) ? value[0] : (value ?? null)
}

function require(obj, prefix, fields) {
  for (const f of fields) if (!String(obj[f] ?? '').trim()) errors.value[`${prefix}.${f}`] = t('validation.required')
}

function validateAddress(address, prefix, withLabel) {
  require(address, prefix, [...(withLabel ? ['label'] : []), 'contact_name', 'contact_phone', 'address_line1', 'city'])
  if (address.contact_phone && !PHONE.test(address.contact_phone)) errors.value[`${prefix}.contact_phone`] = t('validation.phone')
}

/** Client-side checks for fast feedback; the server re-validates everything. */
function validateStep(index) {
  errors.value = {}
  if (index === 0) {
    require(data.company, 'company', ['name', 'business_type', 'contact_person', 'email', 'phone', 'trade_license_no'])
    if (data.company.email && !EMAIL.test(data.company.email)) errors.value['company.email'] = t('validation.email')
    if (data.company.phone && !PHONE.test(data.company.phone)) errors.value['company.phone'] = t('validation.phone')
  }
  if (index === 1) {
    require(data.user, 'user', ['name', 'email', 'phone', 'password'])
    if (data.user.email && !EMAIL.test(data.user.email)) errors.value['user.email'] = t('validation.email')
    if (data.user.phone && !PHONE.test(data.user.phone)) errors.value['user.phone'] = t('validation.phone')
    if (data.user.password && data.user.password.length < 8) errors.value['user.password'] = t('validation.min_length', { min: 8 })
    if (data.user.password !== data.user.password_confirmation) errors.value['user.password_confirmation'] = t('validation.password_mismatch')
  }
  if (index === 2) {
    validateAddress(data.billing_address, 'billing_address', false)
    if (!sameAsBilling.value) data.delivery_addresses.forEach((a, i) => validateAddress(a, `delivery_addresses.${i}`, true))
  }
  if (index === 3) {
    if (!data.documents.trade_license) errors.value['documents.trade_license'] = t('validation.required')
    if (!data.documents.nid) errors.value['documents.nid'] = t('validation.required')
  }
  if (index === 4 && !data.accept_terms) errors.value.accept_terms = t('validation.required')
  return Object.keys(errors.value).length === 0
}

function next() {
  if (validateStep(step.value)) step.value = Math.min(steps.length - 1, step.value + 1)
}

function stepForError(key) {
  if (key.startsWith('company.')) return 0
  if (key.startsWith('user.')) return 1
  if (key.startsWith('billing_address') || key.startsWith('delivery_addresses')) return 2
  if (key.startsWith('documents')) return 3
  return 4
}

function deliveryAddresses() {
  if (!sameAsBilling.value) return data.delivery_addresses
  return [{ ...data.billing_address, label: data.billing_address.label || data.company.trade_name || data.company.name }]
}

function buildFormData() {
  const form = new FormData()
  const append = (key, value) => {
    if (value !== null && value !== undefined && value !== '') form.append(key, value)
  }
  for (const [k, v] of Object.entries(data.company)) append(`company[${k}]`, v)
  for (const [k, v] of Object.entries(data.user)) append(`user[${k}]`, v)
  append('user[locale]', locale.value)
  for (const [k, v] of Object.entries(data.billing_address)) append(`billing_address[${k}]`, v)
  deliveryAddresses().forEach((a, i) => {
    for (const [k, v] of Object.entries(a)) append(`delivery_addresses[${i}][${k}]`, v)
  })
  for (const [k, file] of Object.entries(data.documents)) if (file) form.append(`documents[${k}]`, file)
  form.append('accept_terms', data.accept_terms ? '1' : '0')
  return form
}

async function submit() {
  if (!validateStep(4)) return
  busy.value = true
  formError.value = null
  try {
    const payload = await authApi.register(buildFormData())
    auth.setSession(payload, false)
    localeStore.syncFromUser(payload.user)
    router.replace({ name: 'kyc.status' })
  } catch (e) {
    if (e instanceof ApiError && e.isValidation) {
      errors.value = e.errors
      step.value = Math.min(...Object.keys(e.errors).map(stepForError))
    } else {
      formError.value = e.message
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div class="w-full max-w-2xl py-4">
    <h1 class="text-2xl font-semibold text-ink">{{ t('register.title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ t('register.subtitle') }}</p>

    <ol class="mt-6 flex items-center gap-2 overflow-x-auto pb-1 text-xs" :aria-label="t('register.title')">
      <li v-for="(s, i) in steps" :key="s" class="flex shrink-0 items-center gap-2">
        <span class="flex h-6 w-6 items-center justify-center rounded-full font-semibold" :class="i < step ? 'bg-brand-700 text-white' : i === step ? 'bg-brand-100 text-brand-800 ring-2 ring-brand-600' : 'bg-slate-100 text-slate-500'">
          <AppIcon v-if="i < step" name="Check" :size="12" /><template v-else>{{ i + 1 }}</template>
        </span>
        <span :class="i === step ? 'font-semibold text-ink' : 'text-muted'" :aria-current="i === step ? 'step' : undefined">{{ t(`register.${s}`) }}</span>
        <span v-if="i < steps.length - 1" class="h-px w-6 bg-line" aria-hidden="true" />
      </li>
    </ol>

    <div v-if="formError" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ formError }}</div>

    <form class="mt-6 space-y-6" novalidate @submit.prevent="step === steps.length - 1 ? submit() : next()">
      <section v-show="step === 0" class="grid gap-4 sm:grid-cols-2">
        <BaseInput v-model="data.company.name" class="sm:col-span-2" :label="t('register.company_name')" required :error="err('company.name')" />
        <BaseInput v-model="data.company.trade_name" :label="t('register.trade_name')" :error="err('company.trade_name')" />
        <BaseSelect v-model="data.company.business_type" :label="t('register.business_type')" :options="businessTypes" :placeholder="t('common.none')" required :error="err('company.business_type')" />
        <BaseInput v-model="data.company.contact_person" :label="t('register.contact_person')" required :error="err('company.contact_person')" />
        <BaseInput v-model="data.company.phone" :label="t('register.company_phone')" type="tel" placeholder="01XXXXXXXXX" required :error="err('company.phone')" />
        <BaseInput v-model="data.company.email" class="sm:col-span-2" :label="t('register.company_email')" type="email" required :error="err('company.email')" />
        <BaseInput v-model="data.company.trade_license_no" :label="t('register.trade_license_no')" required :error="err('company.trade_license_no')" />
        <BaseInput v-model="data.company.tin_no" :label="t('register.tin_no')" inputmode="numeric" :error="err('company.tin_no')" />
        <BaseInput v-model="data.company.bin_no" :label="t('register.bin_no')" :error="err('company.bin_no')" />
        <BaseInput v-model="data.company.website" :label="t('register.website')" type="url" placeholder="https://" :error="err('company.website')" />
      </section>

      <section v-show="step === 1" class="grid gap-4 sm:grid-cols-2">
        <BaseInput v-model="data.user.name" :label="t('register.your_name')" autocomplete="name" required :error="err('user.name')" />
        <BaseInput v-model="data.user.designation" :label="t('register.designation')" :error="err('user.designation')" />
        <BaseInput v-model="data.user.email" :label="t('auth.email')" type="email" autocomplete="username" required :error="err('user.email')" />
        <BaseInput v-model="data.user.phone" :label="t('register.mobile')" type="tel" autocomplete="tel" placeholder="01XXXXXXXXX" required :error="err('user.phone')" />
        <BaseInput v-model="data.user.password" :label="t('auth.password')" type="password" autocomplete="new-password" required :error="err('user.password')" />
        <BaseInput v-model="data.user.password_confirmation" :label="t('auth.confirm_password')" type="password" autocomplete="new-password" required :error="err('user.password_confirmation')" />
      </section>

      <section v-show="step === 2" class="space-y-6">
        <div>
          <h2 class="mb-3 text-sm font-semibold">{{ t('register.billing_address') }}</h2>
          <AddressFields v-model="data.billing_address" :with-label="false" :error-for="(f) => err(`billing_address.${f}`)" />
        </div>
        <div>
          <h2 class="mb-3 text-sm font-semibold">{{ t('register.delivery_addresses') }}</h2>
          <label class="flex items-center gap-2 text-sm"><input v-model="sameAsBilling" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" />{{ t('register.same_as_billing') }}</label>
          <template v-if="!sameAsBilling">
            <div v-for="(address, i) in data.delivery_addresses" :key="i" class="card mt-4 p-4">
              <div class="mb-3 flex justify-between">
                <p class="text-sm font-medium">#{{ i + 1 }}</p>
                <button v-if="data.delivery_addresses.length > 1" type="button" class="text-slate-400 hover:text-red-600" :aria-label="t('common.remove')" @click="data.delivery_addresses.splice(i, 1)"><AppIcon name="Trash2" :size="16" /></button>
              </div>
              <AddressFields v-model="data.delivery_addresses[i]" :error-for="(f) => err(`delivery_addresses.${i}.${f}`)" />
            </div>
            <BaseButton class="mt-3" size="sm" variant="secondary" icon="Plus" @click="data.delivery_addresses.push(emptyAddress())">{{ t('register.add_delivery_address') }}</BaseButton>
          </template>
        </div>
      </section>

      <section v-show="step === 3" class="space-y-4">
        <p class="text-sm text-muted">{{ t('register.documents_hint') }}</p>
        <div class="grid gap-4 sm:grid-cols-2">
          <FilePicker v-model="data.documents.trade_license" :label="t('register.trade_license_file')" required :error="err('documents.trade_license')" />
          <FilePicker v-model="data.documents.nid" :label="t('register.nid_file')" required :error="err('documents.nid')" />
          <FilePicker v-model="data.documents.tin_certificate" :label="t('register.tin_file')" :error="err('documents.tin_certificate')" />
          <FilePicker v-model="data.documents.bin_certificate" :label="t('register.bin_file')" :error="err('documents.bin_certificate')" />
        </div>
      </section>

      <section v-show="step === 4" class="space-y-4">
        <dl class="card grid gap-3 p-5 text-sm sm:grid-cols-2">
          <div><dt class="text-muted">{{ t('register.company_name') }}</dt><dd class="font-medium">{{ data.company.name }}</dd></div>
          <div><dt class="text-muted">{{ t('register.business_type') }}</dt><dd>{{ data.company.business_type ? t(`business_type.${data.company.business_type}`) : '—' }}</dd></div>
          <div><dt class="text-muted">{{ t('register.trade_license_no') }}</dt><dd class="font-mono">{{ data.company.trade_license_no }}</dd></div>
          <div><dt class="text-muted">{{ t('register.your_name') }}</dt><dd>{{ data.user.name }} · {{ data.user.email }}</dd></div>
          <div class="sm:col-span-2"><dt class="text-muted">{{ t('register.delivery_addresses') }}</dt><dd>{{ deliveryAddresses().map((a) => a.label || a.city).join(', ') }}</dd></div>
          <div class="sm:col-span-2"><dt class="text-muted">{{ t('kyc.documents') }}</dt><dd>{{ Object.values(data.documents).filter(Boolean).map((f) => f.name).join(', ') }}</dd></div>
        </dl>
        <label class="flex items-start gap-2 text-sm">
          <input v-model="data.accept_terms" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-line text-brand-700" />
          <span>{{ t('register.accept_terms') }}</span>
        </label>
        <p v-if="err('accept_terms')" class="field-error">{{ err('accept_terms') }}</p>
      </section>

      <div class="flex items-center justify-between gap-3 border-t border-line pt-5">
        <BaseButton v-if="step > 0" variant="secondary" icon="ChevronLeft" @click="step--">{{ t('common.back') }}</BaseButton>
        <RouterLink v-else :to="{ name: 'login' }" class="text-sm text-muted hover:text-ink">{{ t('auth.have_account') }} {{ t('auth.sign_in') }}</RouterLink>
        <BaseButton type="submit" :loading="busy">{{ step === steps.length - 1 ? (busy ? t('register.submitting') : t('register.submit')) : t('common.next') }}</BaseButton>
      </div>
    </form>
  </div>
</template>
