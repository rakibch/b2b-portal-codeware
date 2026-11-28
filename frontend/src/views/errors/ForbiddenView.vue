<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'

const { t } = useI18n()
</script>

<template>
  <div class="card">
    <EmptyState icon="ShieldAlert" :title="t('errors.forbidden_title')" :description="t('errors.forbidden')">
      <BaseButton :to="{ name: 'home' }">{{ t('errors.go_home') }}</BaseButton>
    </EmptyState>
  </div>
</template>
