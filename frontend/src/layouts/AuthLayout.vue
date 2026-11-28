<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import LocaleSwitcher from '@/components/layout/LocaleSwitcher.vue'

const { t } = useI18n()
const features = ['auth.feature_tiers', 'auth.feature_credit', 'auth.feature_bangla']
</script>

<template>
  <div class="flex min-h-screen bg-white">
    <section class="relative hidden w-[44%] flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 p-12 text-white lg:flex">
      <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15"><AppIcon name="ShoppingCart" :size="22" /></span>
        <span class="text-lg font-semibold">{{ t('app.name') }}</span>
      </div>
      <div>
        <h2 class="text-3xl leading-tight font-semibold">{{ t('app.tagline') }}</h2>
        <ul class="mt-8 space-y-3">
          <li v-for="key in features" :key="key" class="flex items-center gap-3 text-brand-50">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-white/15"><AppIcon name="Check" :size="14" /></span>
            {{ t(key) }}
          </li>
        </ul>
      </div>
      <p class="text-sm text-brand-100/80">{{ t('auth.trusted_by') }}</p>
      <div class="pointer-events-none absolute -right-24 -bottom-24 h-80 w-80 rounded-full bg-white/5" aria-hidden="true" />
    </section>

    <section class="flex flex-1 flex-col">
      <div class="flex items-center justify-between px-6 py-5 sm:px-10">
        <span class="flex items-center gap-2 text-sm font-semibold text-brand-800 lg:invisible">
          <AppIcon name="ShoppingCart" :size="18" /> {{ t('app.name') }}
        </span>
        <LocaleSwitcher />
      </div>
      <main id="main" class="flex flex-1 items-start justify-center px-6 pb-12 sm:px-10 lg:items-center">
        <RouterView />
      </main>
    </section>
  </div>
</template>
