<script setup>
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { authApi } from '@/api/auth'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const { form, busy, fieldError, formError, submit } = useApiForm({
  token: String(route.query.token ?? ''),
  email: String(route.query.email ?? ''),
  password: '',
  password_confirmation: '',
})

async function onSubmit() {
  try {
    await submit((data) => authApi.resetPassword(data), { successMessage: t('account.password_changed') })
    auth.clear() // every token was revoked server-side
    router.replace({ name: 'login' })
  } catch {
    /* rendered */
  }
}
</script>

<template>
  <div class="w-full max-w-sm">
    <h1 class="text-2xl font-semibold text-ink">{{ t('auth.reset_title') }}</h1>
    <div v-if="formError || fieldError('token')" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
      {{ formError || fieldError('token') }}
    </div>
    <form class="mt-6 space-y-4" novalidate @submit.prevent="onSubmit">
      <BaseInput v-model="form.email" :label="t('auth.email')" type="email" autocomplete="username" required :error="fieldError('email')" />
      <BaseInput v-model="form.password" :label="t('auth.new_password')" type="password" autocomplete="new-password" required :error="fieldError('password')" />
      <BaseInput v-model="form.password_confirmation" :label="t('auth.confirm_password')" type="password" autocomplete="new-password" required />
      <BaseButton type="submit" block size="lg" :loading="busy">{{ t('auth.reset_password') }}</BaseButton>
    </form>
    <p class="mt-8 text-center text-sm">
      <RouterLink :to="{ name: 'login' }" class="font-medium text-brand-700 hover:underline">{{ t('auth.back_to_login') }}</RouterLink>
    </p>
  </div>
</template>
