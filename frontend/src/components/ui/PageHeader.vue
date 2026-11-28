<script setup>
defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: null },
})
</script>

<template>
  <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
      <h1 class="truncate text-xl font-semibold text-ink sm:text-2xl">{{ title }}</h1>
      <p v-if="subtitle" class="mt-1 text-sm text-muted">{{ subtitle }}</p>
    </div>
    <div v-if="$slots.actions" class="flex flex-wrap gap-2"><slot name="actions" /></div>
  </div>
</template>
