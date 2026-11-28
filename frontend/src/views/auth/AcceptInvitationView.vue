<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import { authApi } from '@/api/auth'
import { useApiForm } from '@/composables/useApiForm'
import { useAuthStore } from '@/stores/auth'
import { useLocaleStore } from '@/stores/locale'

const { t, locale } = useI18n()
const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const localeStore = useLocaleStore()
const token = typeof route.query.token === 'string' ? route.query.token : ''
const invitation = ref(null)
const invalid = ref(false)
const { form, busy, formError, fieldError, submit } = useApiForm({ name: '', phone: '', designation: '', password: '', password_confirmation: '' })

onMounted(async () => {
  if (token.length !== 64) {
    invalid.value = true
    return
  }
  try {
    invitation.value = await authApi.invitation(token)
    form.name = invitation.value.name ?? ''
  } catch {
    invalid.value = true
  }
})

async function accept() {
  try {
    const payload = await submit((data) => authApi.acceptInvitation({ ...data, token, locale: locale.value }))
    auth.setSession(payload, false)
    localeStore.syncFromUser(payload.user)
    router.replace({ name: 'home' })
  } catch {
    /* rendered */
  }
}
</script>

<template>
  <div class="w-full max-w-sm">
    <SpinnerBlock v-if="!invitation && !invalid" />
    <EmptyState v-else-if="invalid" icon="ShieldAlert" :title="t('errors.not_found_title')" :description="t('team.invitation_invalid')">
      <BaseButton :to="{ name: 'login' }">{{ t('auth.back_to_login') }}</BaseButton>
    </EmptyState>
    <template v-else>
      <h1 class="text-2xl font-semibold">{{ t('team.accept_title', { company: invitation.company }) }}</h1>
      <p class="mt-2 text-sm text-muted">{{ t('team.accept_subtitle', { inviter: invitation.invited_by, company: invitation.company }) }}</p>
      <div v-if="formError" class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ formError }}</div>
      <form class="mt-6 space-y-4" novalidate @submit.prevent="accept">
        <BaseInput :model-value="invitation.email" :label="t('auth.email')" disabled />
        <BaseInput v-model="form.name" :label="t('register.your_name')" required :error="fieldError('name')" />
        <BaseInput v-model="form.phone" :label="t('register.mobile')" type="tel" placeholder="01XXXXXXXXX" :error="fieldError('phone')" />
        <BaseInput v-model="form.designation" :label="t('register.designation')" :error="fieldError('designation')" />
        <BaseInput v-model="form.password" :label="t('auth.password')" type="password" autocomplete="new-password" required :error="fieldError('password')" />
        <BaseInput v-model="form.password_confirmation" :label="t('auth.confirm_password')" type="password" autocomplete="new-password" required />
        <BaseButton type="submit" block size="lg" :loading="busy">{{ t('team.accept') }}</BaseButton>
      </form>
    </template>
  </div>
</template>
