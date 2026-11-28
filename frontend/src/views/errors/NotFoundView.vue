<script setup>
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import LocaleSwitcher from '@/components/layout/LocaleSwitcher.vue'

const { t } = useI18n()
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-surface p-6">
    <div class="absolute top-5 right-5"><LocaleSwitcher /></div>
    <p class="text-6xl font-bold text-brand-700">404</p>
    <EmptyState icon="Search" :title="t('errors.not_found_title')" :description="t('errors.not_found')">
      <BaseButton :to="{ name: 'home' }">{{ t('errors.go_home') }}</BaseButton>
    </EmptyState>
  </div>
</template>
