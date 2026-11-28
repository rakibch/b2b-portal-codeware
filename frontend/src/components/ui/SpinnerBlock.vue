<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'

const { t } = useI18n()
</script>

<template>
  <div class="flex items-center justify-center gap-2 py-12 text-sm text-muted" role="status">
    <AppIcon name="Loader2" class="animate-spin" :size="18" />
    {{ t('common.loading') }}
  </div>
</template>
