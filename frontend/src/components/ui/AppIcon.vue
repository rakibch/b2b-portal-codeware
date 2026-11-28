<script setup>
import { computed } from 'vue'
import { icons } from '@/utils/icons'

const props = defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 18 },
  strokeWidth: { type: [Number, String], default: 2 },
})

const component = computed(() => icons[props.name] ?? icons.CircleAlert)
</script>

<template>
  <component :is="component" :size="size" :stroke-width="strokeWidth" aria-hidden="true" />
</template>
