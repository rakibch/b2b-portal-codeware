<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'

defineProps({
  label: { type: String, required: true },
  value: { type: String, required: true },
  icon: { type: String, default: null },
  hint: { type: String, default: null },
  to: { type: [Object, String], default: null },
  tone: { type: String, default: 'default' }, // default | warning | danger
})
</script>

<template>
  <component :is="to ? 'RouterLink' : 'div'" :to="to" class="card block p-5 transition-shadow" :class="to ? 'hover:shadow-md' : ''">
    <div class="flex items-center justify-between gap-2">
      <p class="text-xs font-medium text-muted">{{ label }}</p>
      <AppIcon v-if="icon" :name="icon" :size="16" :class="tone === 'danger' ? 'text-red-500' : tone === 'warning' ? 'text-amber-500' : 'text-slate-400'" />
    </div>
    <p class="mt-2 text-2xl font-semibold tabular text-ink">{{ value }}</p>
    <p v-if="hint" class="mt-1 text-xs text-muted">{{ hint }}</p>
  </component>
</template>
