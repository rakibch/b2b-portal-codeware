<script setup>
import { useI18n } from 'vue-i18n'
import { useLocaleStore } from '@/stores/locale'

defineProps({ compact: { type: Boolean, default: false } })

const store = useLocaleStore()
const { t } = useI18n()

const options = [
  { value: 'en', label: 'EN', title: 'English' },
  { value: 'bn', label: 'বাং', title: 'বাংলা' },
]
</script>

<template>
  <div role="group" :aria-label="t('nav.language_switch')" class="inline-flex rounded-lg border border-line bg-white p-0.5 shadow-sm">
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      :title="option.title"
      :lang="option.value"
      :aria-pressed="store.locale === option.value"
      class="rounded-md px-2.5 py-1 text-xs font-semibold transition-colors focus-visible:ring-2 focus-visible:ring-brand-600/40 focus-visible:outline-none"
      :class="[store.locale === option.value ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100', option.value === 'bn' ? 'font-bangla' : '', compact ? 'px-2' : '']"
      @click="store.setLocale(option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
