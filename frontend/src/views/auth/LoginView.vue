<script setup>
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'
import { safeRedirect } from '@/utils/safeRedirect'

const { t } = useI18n()
const auth = useAuthStore()
const locale = useLocaleStore()
const router = useRouter()
const route = useRoute()

const { form, busy, fieldError, formError, submit } = useApiForm({ email: '', password: '', remember: false })

async function onSubmit() {
  try {
    const user = await submit((data) => auth.login(data))
    locale.syncFromUser(user)
    router.replace(safeRedirect(route.query.redirect, '/'))
  } catch {
    /* errors rendered by the form */
  }
}
</script>

<template>
  <div class="w-full max-w-sm">
    <h1 class="text-2xl font-semibold text-ink">{{ t('auth.sign_in_title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ t('auth.sign_in_subtitle') }}</p>

    <div v-if="formError" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ formError }}</div>

    <form class="mt-6 space-y-4" novalidate @submit.prevent="onSubmit">
      <BaseInput v-model="form.email" :label="t('auth.email')" type="email" autocomplete="username" required autofocus :error="fieldError('email')" />
      <BaseInput v-model="form.password" :label="t('auth.password')" type="password" autocomplete="current-password" required :error="fieldError('password')" />

      <div class="flex items-center justify-between gap-4">
        <label class="flex items-center gap-2 text-sm text-slate-600">
          <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-line text-brand-700 focus:ring-brand-600" />
          {{ t('auth.remember_me') }}
        </label>
        <RouterLink :to="{ name: 'forgot-password' }" class="text-sm font-medium whitespace-nowrap text-brand-700 hover:underline">{{ t('auth.forgot_password') }}</RouterLink>
      </div>

      <BaseButton type="submit" block size="lg" :loading="busy">{{ busy ? t('auth.signing_in') : t('auth.sign_in') }}</BaseButton>
    </form>

    <p v-if="$router.hasRoute('register')" class="mt-8 text-center text-sm text-muted">
      {{ t('auth.no_account') }}
      <RouterLink :to="{ name: 'register' }" class="font-medium text-brand-700 hover:underline">{{ t('auth.register_link') }}</RouterLink>
    </p>
  </div>
</template>
