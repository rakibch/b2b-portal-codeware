<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import ReasonDialog from '@/components/shared/ReasonDialog.vue'
import { adminApi } from '@/api/admin'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const props = defineProps({ id: { type: String, required: true } })
const { t } = useI18n()
const { formatCurrency, formatDate, formatNumber } = useFormat()
const auth = useAuthStore()
const toast = useToastStore()
const router = useRouter()

const company = ref(null)
const lookups = ref({ customer_groups: [], account_managers: [] })
const busy = ref(null)
const errors = ref({})
const reasonFor = ref(null) // reject | suspend | doc:<id>
const reasonError = ref(null)
const canManageCredit = computed(() => auth.can('credit.manage'))
const terms = reactive({ credit_limit: '0', payment_terms_days: 30, customer_group_id: null, account_manager_id: null })

async function load() {
  const [c, l] = await Promise.all([adminApi.kycCompany(props.id), adminApi.lookups()])
  company.value = c
  lookups.value = l
  terms.customer_group_id = c.customer_group?.id ?? null
  terms.account_manager_id = c.account_manager?.id ?? null
  if (c.credit) {
    terms.credit_limit = c.credit.credit_limit
    terms.payment_terms_days = c.credit.payment_terms_days || 30
  }
}

async function approve() {
  busy.value = 'approve'
  errors.value = {}
  const payload = { customer_group_id: terms.customer_group_id }
  if (canManageCredit.value) Object.assign(payload, { credit_limit: terms.credit_limit || '0', payment_terms_days: Number(terms.payment_terms_days), account_manager_id: terms.account_manager_id })
  try {
    const result = await adminApi.approveKyc(props.id, payload)
    toast.success(result.message)
    company.value = result.data
  } catch (e) {
    if (e.isValidation) errors.value = e.errors
    else toast.error(e.message)
  } finally {
    busy.value = null
  }
}

async function confirmReason(reason) {
  busy.value = 'reason'
  reasonError.value = null
  try {
    if (reasonFor.value === 'reject') {
      const r = await adminApi.rejectKyc(props.id, reason)
      toast.success(r.message)
    } else if (reasonFor.value === 'suspend') {
      const r = await adminApi.suspendKyc(props.id, reason)
      toast.success(r.message)
    } else {
      const docId = Number(reasonFor.value.split(':')[1])
      await adminApi.reviewDocument(props.id, docId, { status: 'rejected', notes: reason })
    }
    reasonFor.value = null
    await load()
  } catch (e) {
    reasonError.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
  } finally {
    busy.value = null
  }
}

async function verifyDocument(doc) {
  busy.value = `doc-${doc.id}`
  try {
    await adminApi.reviewDocument(props.id, doc.id, { status: 'verified' })
    await load()
  } finally {
    busy.value = null
  }
}

const reasonTitle = computed(() => ({ reject: t('kyc.reject'), suspend: t('kyc.suspend') })[reasonFor.value] ?? t('kyc.reject_document'))
const status = computed(() => company.value?.kyc.status.value)

onMounted(load)
</script>

<template>
  <SpinnerBlock v-if="!company" />
  <div v-else>
    <button type="button" class="mb-4 inline-flex items-center gap-1 text-sm text-muted hover:text-ink" @click="router.back()"><AppIcon name="ChevronLeft" :size="16" />{{ t('nav.kyc_queue') }}</button>

    <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <h1 class="text-2xl font-semibold">{{ company.name }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted">
          <StatusBadge :status="company.kyc.status" />
          <span>{{ company.business_type.label }}</span>
          <span>· {{ t('kyc.submitted_on', { date: formatDate(company.kyc.submitted_at) }) }}</span>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <BaseButton v-if="status === 'pending'" variant="secondary" icon="XCircle" @click="reasonFor = 'reject'">{{ t('kyc.reject') }}</BaseButton>
        <BaseButton v-if="status === 'approved'" variant="danger" icon="ShieldAlert" @click="reasonFor = 'suspend'">{{ t('kyc.suspend') }}</BaseButton>
        <BaseButton v-if="status === 'approved' && $router.hasRoute('admin.company')" variant="secondary" :to="{ name: 'admin.company', params: { id: company.id } }">{{ t('nav.companies') }}</BaseButton>
      </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <section class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('common.details') }}</h2>
          <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-muted">{{ t('register.trade_name') }}</dt><dd>{{ company.trade_name || '—' }}</dd></div>
            <div><dt class="text-muted">{{ t('register.contact_person') }}</dt><dd>{{ company.contact_person }}</dd></div>
            <div><dt class="text-muted">{{ t('register.company_phone') }}</dt><dd>{{ company.phone }}</dd></div>
            <div><dt class="text-muted">{{ t('register.company_email') }}</dt><dd class="break-all">{{ company.email }}</dd></div>
            <div><dt class="text-muted">{{ t('register.trade_license_no') }}</dt><dd class="font-mono">{{ company.trade_license_no }}</dd></div>
            <div><dt class="text-muted">{{ t('register.tin_no') }} / {{ t('register.bin_no') }}</dt><dd class="font-mono">{{ company.tin_no || '—' }} / {{ company.bin_no || '—' }}</dd></div>
            <div v-if="company.website" class="sm:col-span-2"><dt class="text-muted">{{ t('register.website') }}</dt><dd class="break-all">{{ company.website }}</dd></div>
          </dl>
          <div v-if="company.kyc.rejection_reason" class="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800"><span class="font-semibold">{{ t('kyc.reason') }}:</span> {{ company.kyc.rejection_reason }}</div>
        </section>

        <section class="card">
          <h2 class="border-b border-line px-5 py-3 text-sm font-semibold">{{ t('admin.inspect_documents') }}</h2>
          <ul class="divide-y divide-line">
            <li v-for="d in company.documents" :key="d.id" class="flex flex-col gap-3 px-5 py-3 sm:flex-row sm:items-center">
              <div class="min-w-0 flex-1">
                <p class="font-medium">{{ d.type.label }}</p>
                <p class="truncate text-xs text-muted">{{ d.original_name }} · {{ formatNumber(Math.round(d.size / 1024)) }} KB · {{ formatDate(d.uploaded_at) }}</p>
                <p v-if="d.review_notes" class="text-xs text-red-600">{{ d.review_notes }}</p>
              </div>
              <StatusBadge :status="d.status" />
              <div class="flex gap-1">
                <BaseButton size="sm" variant="ghost" icon="Eye" @click="adminApi.downloadDocument(d.id, d.original_name)">{{ t('common.download') }}</BaseButton>
                <template v-if="d.status.value === 'pending'">
                  <BaseButton size="sm" variant="secondary" icon="Check" :loading="busy === `doc-${d.id}`" @click="verifyDocument(d)">{{ t('kyc.verify_document') }}</BaseButton>
                  <BaseButton size="sm" variant="ghost" @click="reasonFor = `doc:${d.id}`">{{ t('kyc.reject') }}</BaseButton>
                </template>
              </div>
            </li>
          </ul>
        </section>

        <section class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('addresses.title') }}</h2>
          <ul class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
            <li v-for="a in company.addresses" :key="a.id" class="rounded-lg border border-line p-3">
              <p class="font-medium">{{ a.label }} <span class="text-xs text-muted">· {{ a.type.label }}</span></p>
              <p class="text-muted">{{ a.contact_name }} · {{ a.contact_phone }}</p>
              <p class="text-muted">{{ a.full_address }}</p>
            </li>
          </ul>
        </section>
      </div>

      <aside v-if="['pending', 'rejected', 'suspended'].includes(status)" class="card h-fit space-y-4 p-5">
        <h2 class="text-sm font-semibold">{{ t('admin.approve_with_terms') }}</h2>
        <div>
          <label for="group" class="label">{{ t('kyc.customer_group') }}</label>
          <select id="group" v-model="terms.customer_group_id" class="input">
            <option :value="null">{{ t('common.none') }}</option>
            <option v-for="g in lookups.customer_groups" :key="g.id" :value="g.id">{{ g.name }}</option>
          </select>
        </div>
        <template v-if="canManageCredit">
          <BaseInput v-model="terms.credit_limit" :label="t('credit.limit')" type="number" min="0" step="1000" :error="errors.credit_limit?.[0]" />
          <BaseInput v-model="terms.payment_terms_days" :label="t('kyc.terms_days')" type="number" min="0" max="365" :error="errors.payment_terms_days?.[0]" />
          <div>
            <label for="manager" class="label">{{ t('kyc.assign_manager') }}</label>
            <select id="manager" v-model="terms.account_manager_id" class="input">
              <option :value="null">{{ t('common.none') }}</option>
              <option v-for="m in lookups.account_managers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
            <p v-if="errors.account_manager_id" class="field-error">{{ errors.account_manager_id[0] }}</p>
          </div>
        </template>
        <p v-else class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-muted">{{ t('kyc.credit_by_admin') }}</p>
        <BaseButton block icon="ShieldCheck" :loading="busy === 'approve'" :disabled="company.documents.some((d) => d.status.value === 'rejected')" @click="approve">{{ t('kyc.approve') }}</BaseButton>
        <p v-if="company.documents.some((d) => d.status.value === 'rejected')" class="text-xs text-amber-700">{{ t('kyc.rejected_docs_block') }}</p>
      </aside>
      <aside v-else-if="company.credit" class="card h-fit p-5 text-sm">
        <h2 class="font-semibold">{{ t('kyc.credit_terms') }}</h2>
        <dl class="mt-3 space-y-2">
          <div class="flex justify-between"><dt class="text-muted">{{ t('credit.limit') }}</dt><dd class="tabular">{{ formatCurrency(company.credit.credit_limit) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">{{ t('credit.terms') }}</dt><dd>{{ t('credit.net_days', { days: formatNumber(company.credit.payment_terms_days) }) }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">{{ t('kyc.customer_group') }}</dt><dd>{{ company.customer_group?.name ?? '—' }}</dd></div>
          <div class="flex justify-between"><dt class="text-muted">{{ t('kyc.assign_manager') }}</dt><dd>{{ company.account_manager?.name ?? '—' }}</dd></div>
        </dl>
      </aside>
    </div>

    <ReasonDialog :open="Boolean(reasonFor)" :title="reasonTitle" :loading="busy === 'reason'" :error="reasonError" @update:open="!$event && (reasonFor = null)" @confirm="confirmReason" />
  </div>
</template>
