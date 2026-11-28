<script setup>
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'

/** Modal that collects a mandatory reason (reject / suspend / decline / cancel). */
const open = defineModel('open', { type: Boolean, default: false })
const props = defineProps({
  title: { type: String, required: true },
  message: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: null },
  minLength: { type: Number, default: 5 },
})
const emit = defineEmits(['confirm'])
const { t } = useI18n()
const reason = ref('')

watch(open, (value) => {
  if (value) reason.value = ''
})
</script>

<template>
  <BaseModal v-model:open="open" :title="title" size="sm">
    <p v-if="message" class="mb-3 text-sm text-muted">{{ message }}</p>
    <div v-if="error" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ error }}</div>
    <label for="reason-input" class="label">{{ t('common.reason') }}</label>
    <textarea id="reason-input" v-model="reason" rows="3" maxlength="2000" class="input" />
    <template #footer>
      <BaseButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</BaseButton>
      <BaseButton variant="danger" :loading="loading" :disabled="reason.trim().length < props.minLength" @click="emit('confirm', reason.trim())">{{ confirmLabel ?? t('common.confirm') }}</BaseButton>
    </template>
  </BaseModal>
</template>
