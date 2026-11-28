<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import CreditMeter from '@/components/buyer/CreditMeter.vue'
import { buyerApi } from '@/api/buyer'
import { useApiForm } from '@/composables/useApiForm'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'

const { t, locale } = useI18n()
const { formatDate } = useFormat()
const auth = useAuthStore()
const company = ref(null)
const canEdit = computed(() => auth.can('company.manage_profile'))
const { form, busy, formError, fieldError, submit, reset } = useApiForm({ trade_name: '', contact_person: '', email: '', phone: '', website: '' })

const credit = computed(() => company.value?.credit && {
  enabled: company.value.credit.credit_enabled,
  on_hold: company.value.credit.credit_on_hold,
  limit: company.value.credit.credit_limit,
  available: company.value.credit.available_credit,
  used: String(Math.max(0, Number(company.value.credit.credit_limit) - Number(company.value.credit.available_credit))),
  payment_terms_days: company.value.credit.payment_terms_days,
})

async function load() {
  company.value = await buyerApi.company()
  reset({ trade_name: company.value.trade_name ?? '', contact_person: company.value.contact_person, email: company.value.email, phone: company.value.phone, website: company.value.website ?? '' })
}

async function save() {
  try {
    company.value = await submit((data) => buyerApi.updateCompany(data), { successMessage: t('common.saved') })
  } catch {
    /* rendered */
  }
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <div>
    <PageHeader :title="t('nav.company')" />
    <SpinnerBlock v-if="!company" />
    <div v-else class="grid gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        <section class="card p-5">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold">{{ company.name }}</h2>
            <StatusBadge :status="company.kyc.status" />
          </div>
          <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
            <div><dt class="text-muted">{{ t('register.business_type') }}</dt><dd>{{ company.business_type.label }}</dd></div>
            <div><dt class="text-muted">{{ t('register.trade_license_no') }}</dt><dd class="font-mono">{{ company.trade_license_no }}</dd></div>
            <div v-if="company.tin_no"><dt class="text-muted">{{ t('register.tin_no') }}</dt><dd class="font-mono">{{ company.tin_no }}</dd></div>
            <div v-if="company.bin_no"><dt class="text-muted">{{ t('register.bin_no') }}</dt><dd class="font-mono">{{ company.bin_no }}</dd></div>
            <div v-if="company.customer_group"><dt class="text-muted">{{ t('kyc.customer_group') }}</dt><dd>{{ company.customer_group.name }}</dd></div>
            <div v-if="company.account_manager"><dt class="text-muted">{{ t('kyc.assign_manager') }}</dt><dd>{{ company.account_manager.name }}<span class="block text-xs text-muted">{{ company.account_manager.phone }}</span></dd></div>
          </dl>
        </section>

        <section class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('common.details') }}</h2>
          <div v-if="formError" class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ formError }}</div>
          <form class="mt-4 grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="save">
            <BaseInput v-model="form.trade_name" :label="t('register.trade_name')" :disabled="!canEdit" :error="fieldError('trade_name')" />
            <BaseInput v-model="form.contact_person" :label="t('register.contact_person')" :disabled="!canEdit" :error="fieldError('contact_person')" />
            <BaseInput v-model="form.email" :label="t('register.company_email')" type="email" :disabled="!canEdit" :error="fieldError('email')" />
            <BaseInput v-model="form.phone" :label="t('register.company_phone')" type="tel" :disabled="!canEdit" :error="fieldError('phone')" />
            <BaseInput v-model="form.website" class="sm:col-span-2" :label="t('register.website')" type="url" :disabled="!canEdit" :error="fieldError('website')" />
            <div v-if="canEdit" class="sm:col-span-2"><BaseButton type="submit" :loading="busy">{{ t('common.save_changes') }}</BaseButton></div>
          </form>
        </section>
      </div>
      <aside class="space-y-6">
        <section v-if="credit" class="card p-5"><CreditMeter :credit="credit" /></section>
        <section class="card p-5">
          <h2 class="text-sm font-semibold">{{ t('kyc.documents') }}</h2>
          <ul class="mt-3 divide-y divide-line text-sm">
            <li v-for="d in company.documents" :key="d.id" class="flex items-center justify-between gap-2 py-2">
              <div class="min-w-0"><p class="truncate">{{ d.type.label }}</p><p class="text-xs text-muted">{{ formatDate(d.uploaded_at) }}</p></div>
              <StatusBadge :status="d.status" />
              <BaseButton v-if="canEdit" size="sm" variant="ghost" icon="FileText" :aria-label="t('common.download')" @click="buyerApi.downloadDocument(d.id, d.original_name)" />
            </li>
          </ul>
        </section>
      </aside>
    </div>
  </div>
</template>
