<script setup>
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import { buyerApi } from '@/api/buyer'
import { useApiForm } from '@/composables/useApiForm'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const { t } = useI18n()
const { formatCurrency, formatDate } = useFormat()
const auth = useAuthStore()
const toast = useToastStore()
const members = ref(null)
const invitations = ref([])
const inviteOpen = ref(false)
const editing = ref(null)

const invite = useApiForm({ email: '', name: '', role: 'buyer_staff', order_approval_limit: '' })
const edit = useApiForm({ role: 'buyer_staff', order_approval_limit: '', designation: '' })

async function load() {
  const [m, i] = await Promise.all([buyerApi.members(), buyerApi.invitations()])
  members.value = m
  invitations.value = i.filter((x) => x.status === 'pending')
}

async function sendInvite() {
  try {
    const result = await invite.submit((data) => buyerApi.invite({ ...data, order_approval_limit: data.order_approval_limit || null }))
    toast.success(result.message)
    inviteOpen.value = false
    invite.reset()
    load()
  } catch {
    /* rendered */
  }
}

function openEdit(member) {
  editing.value = member
  edit.reset({ role: member.roles?.[0] ?? 'buyer_staff', order_approval_limit: member.order_approval_limit ?? '', designation: member.designation ?? '' })
}

async function saveMember() {
  try {
    await edit.submit((data) => buyerApi.updateMember(editing.value.id, { ...data, order_approval_limit: data.order_approval_limit || null }), { successMessage: t('common.saved') })
    editing.value = null
    load()
  } catch {
    /* rendered */
  }
}

async function toggleActive(member) {
  try {
    await buyerApi.updateMember(member.id, { is_active: !member.is_active })
    load()
  } catch (e) {
    toast.error(e.message)
  }
}

async function revoke(invitation) {
  await buyerApi.revokeInvitation(invitation.id)
  load()
}

onMounted(load)
</script>

<template>
  <div>
    <PageHeader :title="t('team.title')">
      <template #actions><BaseButton icon="Plus" @click="inviteOpen = true">{{ t('team.invite') }}</BaseButton></template>
    </PageHeader>
    <SpinnerBlock v-if="!members" />
    <template v-else>
      <section class="card divide-y divide-line">
        <article v-for="m in members" :key="m.id" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center">
          <div class="min-w-0 flex-1">
            <p class="font-medium">{{ m.name }} <span v-if="m.id === auth.user.id" class="text-xs text-muted">({{ t('common.you') }})</span></p>
            <p class="text-xs text-muted">{{ m.email }}<span v-if="m.designation"> · {{ m.designation }}</span></p>
          </div>
          <span class="text-xs text-slate-600">{{ t(`roles.${m.roles?.[0]}`) }}</span>
          <span class="text-xs text-muted tabular">{{ t('account.approval_limit') }}: {{ m.order_approval_limit ? formatCurrency(m.order_approval_limit) : t('account.no_limit') }}</span>
          <StatusBadge :status="m.is_active ? 'active' : 'inactive'" :label="m.is_active ? t('status.active') : t('status.inactive')" />
          <div v-if="m.id !== auth.user.id" class="flex gap-1">
            <BaseButton size="sm" variant="ghost" @click="openEdit(m)">{{ t('common.edit') }}</BaseButton>
            <BaseButton size="sm" variant="ghost" @click="toggleActive(m)">{{ m.is_active ? t('team.deactivate') : t('team.activate') }}</BaseButton>
          </div>
        </article>
      </section>

      <section v-if="invitations.length" class="mt-6">
        <h2 class="mb-3 text-sm font-semibold text-muted">{{ t('team.invitations') }}</h2>
        <div class="card divide-y divide-line">
          <div v-for="i in invitations" :key="i.id" class="flex flex-col gap-2 px-5 py-3 text-sm sm:flex-row sm:items-center">
            <p class="flex-1">{{ i.email }} <span class="text-xs text-muted">· {{ t(`roles.${i.role}`) }} · {{ t('team.expires', { date: formatDate(i.expires_at) }) }}</span></p>
            <BaseButton size="sm" variant="ghost" @click="revoke(i)">{{ t('team.revoke') }}</BaseButton>
          </div>
        </div>
      </section>
    </template>

    <BaseModal v-model:open="inviteOpen" :title="t('team.invite')">
      <form id="invite-form" class="space-y-4" novalidate @submit.prevent="sendInvite">
        <div v-if="invite.formError.value" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ invite.formError.value }}</div>
        <BaseInput v-model="invite.form.email" :label="t('auth.email')" type="email" required :error="invite.fieldError('email')" />
        <BaseInput v-model="invite.form.name" :label="t('register.your_name')" :error="invite.fieldError('name')" />
        <div>
          <label for="inv-role" class="label">{{ t('account.role') }}</label>
          <select id="inv-role" v-model="invite.form.role" class="input"><option value="buyer_staff">{{ t('team.role_staff') }}</option><option value="buyer_admin">{{ t('team.role_admin') }}</option></select>
        </div>
        <BaseInput v-model="invite.form.order_approval_limit" :label="t('account.approval_limit')" type="number" min="0" step="0.01" :hint="t('team.approval_limit_hint')" :error="invite.fieldError('order_approval_limit')" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="inviteOpen = false">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" form="invite-form" :loading="invite.busy.value">{{ t('team.invite') }}</BaseButton>
      </template>
    </BaseModal>

    <BaseModal :open="Boolean(editing)" :title="editing?.name ?? ''" @update:open="!$event && (editing = null)">
      <form id="member-form" class="space-y-4" novalidate @submit.prevent="saveMember">
        <div v-if="edit.formError.value" class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ edit.formError.value }}</div>
        <div>
          <label for="mem-role" class="label">{{ t('account.role') }}</label>
          <select id="mem-role" v-model="edit.form.role" class="input"><option value="buyer_staff">{{ t('team.role_staff') }}</option><option value="buyer_admin">{{ t('team.role_admin') }}</option></select>
        </div>
        <BaseInput v-model="edit.form.designation" :label="t('register.designation')" :error="edit.fieldError('designation')" />
        <BaseInput v-model="edit.form.order_approval_limit" :label="t('account.approval_limit')" type="number" min="0" step="0.01" :hint="t('team.approval_limit_hint')" :error="edit.fieldError('order_approval_limit')" />
      </form>
      <template #footer>
        <BaseButton variant="secondary" @click="editing = null">{{ t('common.cancel') }}</BaseButton>
        <BaseButton type="submit" form="member-form" :loading="edit.busy.value">{{ t('common.save') }}</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
