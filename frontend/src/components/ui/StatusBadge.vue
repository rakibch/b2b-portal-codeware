<script setup>
import { computed } from 'vue'

/** Renders an API enum ({ value, label }) or plain value with a consistent colour per status. */
const props = defineProps({
  status: { type: [Object, String], required: true },
  label: { type: String, default: null },
})

const TONES = {
  green: ['approved', 'verified', 'paid', 'delivered', 'accepted', 'converted', 'in_stock', 'active', 'issued'],
  blue: ['confirmed', 'processing', 'quoted', 'dispatched', 'under_review', 'packing'],
  amber: ['pending', 'submitted', 'partially_paid', 'partially_dispatched', 'pending_verification', 'low_stock', 'unpaid'],
  red: ['rejected', 'cancelled', 'suspended', 'failed', 'void', 'expired', 'out_of_stock', 'overdue', 'returned'],
}

const STYLES = {
  green: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
  blue: 'bg-sky-50 text-sky-700 ring-sky-600/20',
  amber: 'bg-amber-50 text-amber-800 ring-amber-600/20',
  red: 'bg-red-50 text-red-700 ring-red-600/20',
  slate: 'bg-slate-100 text-slate-700 ring-slate-500/20',
}

const value = computed(() => (typeof props.status === 'string' ? props.status : props.status?.value))
const text = computed(() => props.label ?? (typeof props.status === 'string' ? props.status : props.status?.label))
const tone = computed(() => Object.keys(TONES).find((k) => TONES[k].includes(value.value)) ?? 'slate')
</script>

<template>
  <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap ring-1 ring-inset" :class="STYLES[tone]">
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70" aria-hidden="true" />
    {{ text }}
  </span>
</template>
