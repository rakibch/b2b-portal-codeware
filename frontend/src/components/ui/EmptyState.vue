<script setup>
import AppIcon from './AppIcon.vue'

defineProps({
  icon: { type: String, default: 'Package' },
  title: { type: String, required: true },
  description: { type: String, default: null },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
    <div class="mb-3 rounded-full bg-slate-100 p-3 text-slate-400"><AppIcon :name="icon" :size="24" /></div>
    <p class="text-sm font-semibold text-ink">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-muted">{{ description }}</p>
    <div v-if="$slots.default" class="mt-4"><slot /></div>
  </div>
</template>
