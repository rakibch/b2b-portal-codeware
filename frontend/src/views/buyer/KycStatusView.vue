<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import FilePicker from '@/components/shared/FilePicker.vue'
import { buyerApi } from '@/api/buyer'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const { t, locale } = useI18n()
const { formatDate } = useFormat()
const auth = useAuthStore()
const router = useRouter()
const toast = useToastStore()
const company = ref(null)
const files = reactive({ trade_license: null, nid: null, tin_certificate: null, bin_certificate: null })
const uploading = ref(false)
const uploadError = ref(null)

const status = computed(() => company.value?.kyc?.status?.value ?? auth.kycStatus)
const canUpload = computed(() => ['pending', 'rejected'].includes(status.value) && auth.can('company.manage_profile'))
const icon = computed(() => ({ pending: 'ShieldCheck', rejected: 'ShieldAlert', suspended: 'ShieldAlert' })[status.value] ?? 'ShieldCheck')

async function load() {
  company.value = await buyerApi.company()
  // Approved meanwhile? Refresh the session and go to the dashboard.
  if (company.value.kyc.status.value === 'approved') {
    await auth.fetchMe()
    router.replace({ name: 'buyer.dashboard' })
  }
}

async function upload() {
  uploading.value = true
  uploadError.value = null
  try {
    const body = new FormData()
    for (const [type, file] of Object.entries(files)) if (file) body.append(`documents[${type}]`, file)
    const result = await buyerApi.submitDocuments(body)
    toast.success(result.message)
    Object.keys(files).forEach((k) => (files[k] = null))
    await auth.fetchMe()
    await load()
  } catch (e) {
    uploadError.value = e.isValidation ? Object.values(e.errors).flat()[0] : e.message
  } finally {
    uploading.value = false
  }
}

onMounted(load)
watch(locale, load)
</script>

<template>
  <SpinnerBlock v-if="!company" />
  <div v-else class="mx-auto max-w-2xl space-y-6">
    <section class="card p-6 text-center sm:p-8">
      <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full" :class="status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-red-50 text-red-600'"><AppIcon :name="icon" :size="28" /></div>
      <h1 class="mt-4 text-xl font-semibold">{{ t(`kyc.${status}_title`) }}</h1>
      <p class="mt-2 text-sm text-muted">{{ t(`kyc.${status}_body`) }}</p>
      <p v-if="company.kyc.submitted_at" class="mt-3 text-xs text-muted">{{ t('kyc.submitted_on', { date: formatDate(company.kyc.submitted_at) }) }}</p>
      <div v-if="company.kyc.rejection_reason" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-left text-sm text-red-800">
        <p class="font-semibold">{{ t('kyc.reason') }}</p>
        <p>{{ company.kyc.rejection_reason }}</p>
      </div>
      <div class="mt-6 flex justify-center gap-2">
        <BaseButton variant="secondary" icon="Store" :to="{ name: 'buyer.catalog' }">{{ t('kyc.browse_catalog') }}</BaseButton>
        <BaseButton variant="ghost" @click="load">{{ t('common.refresh') }}</BaseButton>
      </div>
    </section>

    <section class="card p-6">
      <h2 class="text-sm font-semibold">{{ t('kyc.documents') }}</h2>
      <ul class="mt-3 divide-y divide-line text-sm">
        <li v-for="d in company.documents" :key="d.id" class="flex items-center justify-between gap-2 py-2.5">
          <div><p>{{ d.type.label }}</p><p v-if="d.review_notes" class="text-xs text-red-600">{{ d.review_notes }}</p></div>
          <StatusBadge :status="d.status" />
        </li>
      </ul>

      <form v-if="canUpload" class="mt-6 space-y-4 border-t border-line pt-6" @submit.prevent="upload">
        <h3 class="text-sm font-semibold">{{ t('kyc.resubmit') }}</h3>
        <p class="text-xs text-muted">{{ t('register.documents_hint') }}</p>
        <div v-if="uploadError" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ uploadError }}</div>
        <div class="grid gap-4 sm:grid-cols-2">
          <FilePicker v-model="files.trade_license" :label="t('register.trade_license_file')" />
          <FilePicker v-model="files.nid" :label="t('register.nid_file')" />
          <FilePicker v-model="files.tin_certificate" :label="t('register.tin_file')" />
          <FilePicker v-model="files.bin_certificate" :label="t('register.bin_file')" />
        </div>
        <BaseButton type="submit" icon="Upload" :loading="uploading" :disabled="!Object.values(files).some(Boolean)">{{ t('common.upload') }}</BaseButton>
      </form>
    </section>
  </div>
</template>
