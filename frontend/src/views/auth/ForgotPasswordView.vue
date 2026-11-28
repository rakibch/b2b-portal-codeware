<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { authApi } from '@/api/auth'
import { useApiForm } from '@/composables/useApiForm'

const { t } = useI18n()
const sent = ref(null)
const { form, busy, fieldError, formError, submit } = useApiForm({ email: '' })

async function onSubmit() {
  try {
    const result = await submit((data) => authApi.forgotPassword(data.email))
    sent.value = result.message
  } catch {
    /* rendered */
  }
}
</script>

<template>
  <div class="w-full max-w-sm">
    <h1 class="text-2xl font-semibold text-ink">{{ t('auth.forgot_title') }}</h1>
    <p class="mt-2 text-sm text-muted">{{ t('auth.forgot_subtitle') }}</p>

    <div v-if="sent" class="mt-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ sent }}</div>
    <div v-if="formError" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ formError }}</div>

    <form v-if="!sent" class="mt-6 space-y-4" novalidate @submit.prevent="onSubmit">
      <BaseInput v-model="form.email" :label="t('auth.email')" type="email" autocomplete="email" required autofocus :error="fieldError('email')" />
      <BaseButton type="submit" block size="lg" :loading="busy">{{ t('auth.send_reset_link') }}</BaseButton>
    </form>

    <p class="mt-8 text-center text-sm">
      <RouterLink :to="{ name: 'login' }" class="font-medium text-brand-700 hover:underline">{{ t('auth.back_to_login') }}</RouterLink>
    </p>
  </div>
</template>
