<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import ConfirmDialog from '@/components/ui/ConfirmDialog.vue'
import LocaleSwitcher from '@/components/layout/LocaleSwitcher.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { authApi } from '@/api/auth'
import { useApiForm } from '@/composables/useApiForm'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const auth = useAuthStore()
const router = useRouter()
const { formatCurrency, formatDateTime } = useFormat()
const confirmSignOut = ref(false)
const signingOut = ref(false)

const user = computed(() => auth.user)
const roleLabels = computed(() => (user.value?.roles ?? []).map((role) => t(`roles.${role}`)))

const password = useApiForm({ current_password: '', password: '', password_confirmation: '' })

async function changePassword() {
  try {
    await password.submit((data) => authApi.changePassword(data), { successMessage: t('account.password_changed') })
    password.reset()
  } catch {
    /* rendered */
  }
}

async function signOutEverywhere() {
  signingOut.value = true
  try {
    await auth.logoutEverywhere()
    router.replace({ name: 'login' })
  } finally {
    signingOut.value = false
  }
}
</script>

<template>
  <div>
    <PageHeader :title="t('account.title')" />

    <div class="grid gap-6 lg:grid-cols-3">
      <section class="card p-5 lg:col-span-1">
        <h2 class="text-sm font-semibold text-ink">{{ t('account.profile') }}</h2>
        <dl class="mt-4 space-y-3 text-sm">
          <div><dt class="text-muted">{{ t('register.your_name') }}</dt><dd class="font-medium">{{ user?.name }}</dd></div>
          <div><dt class="text-muted">{{ t('auth.email') }}</dt><dd class="font-medium break-all">{{ user?.email }}</dd></div>
          <div v-if="user?.phone"><dt class="text-muted">{{ t('register.mobile') }}</dt><dd class="font-medium">{{ user.phone }}</dd></div>
          <div v-if="auth.company">
            <dt class="text-muted">{{ t('account.company') }}</dt>
            <dd class="flex flex-wrap items-center gap-2 font-medium">{{ auth.company.name }} <StatusBadge :status="auth.company.kyc.status" /></dd>
          </div>
          <div>
            <dt class="text-muted">{{ t('account.role') }}</dt>
            <dd class="font-medium">{{ roleLabels.join(', ') }}</dd>
          </div>
          <div v-if="auth.isBuyer">
            <dt class="text-muted">{{ t('account.approval_limit') }}</dt>
            <dd class="font-medium tabular">{{ user?.order_approval_limit ? formatCurrency(user.order_approval_limit) : t('account.no_limit') }}</dd>
          </div>
          <div><dt class="text-muted">{{ t('account.last_login') }}</dt><dd class="font-medium">{{ formatDateTime(user?.last_login_at) }}</dd></div>
        </dl>
      </section>

      <div class="space-y-6 lg:col-span-2">
        <section class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ t('account.preferences') }}</h2>
          <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p class="text-sm font-medium">{{ t('common.language') }}</p>
              <p class="text-xs text-muted">{{ t('account.language_hint') }}</p>
            </div>
            <LocaleSwitcher />
          </div>
        </section>

        <section class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ t('account.change_password') }}</h2>
          <div v-if="password.formError.value" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ password.formError.value }}</div>
          <form class="mt-4 grid gap-4 sm:grid-cols-2" novalidate @submit.prevent="changePassword">
            <BaseInput v-model="password.form.current_password" class="sm:col-span-2" :label="t('account.current_password')" type="password" autocomplete="current-password" required :error="password.fieldError('current_password')" />
            <BaseInput v-model="password.form.password" :label="t('auth.new_password')" type="password" autocomplete="new-password" required :error="password.fieldError('password')" />
            <BaseInput v-model="password.form.password_confirmation" :label="t('auth.confirm_password')" type="password" autocomplete="new-password" required />
            <div class="sm:col-span-2"><BaseButton type="submit" :loading="password.busy.value">{{ t('account.change_password') }}</BaseButton></div>
          </form>
        </section>

        <section class="card p-5">
          <h2 class="text-sm font-semibold text-ink">{{ t('account.security') }}</h2>
          <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-muted">{{ t('account.sign_out_everywhere_hint') }}</p>
            <BaseButton variant="secondary" icon="LogOut" @click="confirmSignOut = true">{{ t('account.sign_out_everywhere') }}</BaseButton>
          </div>
        </section>
      </div>
    </div>

    <ConfirmDialog v-model:open="confirmSignOut" :title="t('account.sign_out_everywhere')" :message="t('account.sign_out_everywhere_hint')" danger :loading="signingOut" @confirm="signOutEverywhere" />
  </div>
</template>
