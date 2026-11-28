<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | ghost | danger | link
  size: { type: String, default: 'md' }, // sm | md | lg
  type: { type: String, default: 'button' },
  loading: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  icon: { type: String, default: null },
  block: { type: Boolean, default: false },
  to: { type: [String, Object], default: null },
})

const variants = {
  primary: 'bg-brand-700 text-white hover:bg-brand-800 focus-visible:ring-brand-600/40 shadow-sm',
  secondary: 'bg-white text-slate-700 border border-line hover:bg-slate-50 focus-visible:ring-slate-400/30 shadow-sm',
  ghost: 'text-slate-600 hover:bg-slate-100 focus-visible:ring-slate-400/30',
  danger: 'bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500/40 shadow-sm',
  link: 'text-brand-700 hover:text-brand-800 underline-offset-4 hover:underline px-0',
}

const sizes = {
  sm: 'h-8 px-3 text-xs gap-1.5',
  md: 'h-10 px-4 text-sm gap-2',
  lg: 'h-12 px-5 text-base gap-2',
}

const classes = computed(() => [
  'inline-flex items-center justify-center rounded-lg font-medium transition-colors focus-visible:outline-none focus-visible:ring-4 disabled:cursor-not-allowed disabled:opacity-60',
  variants[props.variant] ?? variants.primary,
  props.variant === 'link' ? '' : sizes[props.size] ?? sizes.md,
  props.block ? 'w-full' : '',
])
</script>

<template>
  <RouterLink v-if="to && !disabled" :to="to" :class="classes">
    <AppIcon v-if="icon" :name="icon" :size="size === 'sm' ? 14 : 16" />
    <slot />
  </RouterLink>
  <button v-else :type="type" :class="classes" :disabled="disabled || loading" :aria-busy="loading">
    <AppIcon v-if="loading" name="Loader2" class="animate-spin" :size="size === 'sm' ? 14 : 16" />
    <AppIcon v-else-if="icon" :name="icon" :size="size === 'sm' ? 14 : 16" />
    <slot />
  </button>
</template>
