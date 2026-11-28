<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from './BaseButton.vue'
import BaseModal from './BaseModal.vue'

const open = defineModel('open', { type: Boolean, default: false })

defineProps({
  title: { type: String, required: true },
  message: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  danger: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm'])
const { t } = useI18n()
</script>

<template>
  <BaseModal v-model:open="open" :title="title" size="sm">
    <p v-if="message" class="text-sm text-slate-600">{{ message }}</p>
    <slot />
    <template #footer>
      <BaseButton variant="secondary" @click="open = false">{{ t('common.cancel') }}</BaseButton>
      <BaseButton :variant="danger ? 'danger' : 'primary'" :loading="loading" @click="emit('confirm')">
        {{ confirmLabel ?? t('common.confirm') }}
      </BaseButton>
    </template>
  </BaseModal>
</template>
