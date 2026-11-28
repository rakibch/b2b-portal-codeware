<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useFormat } from '@/composables/useFormat'

/**
 * Single-series column chart (daily order value). Spec: ≤24px columns with a 4px rounded top
 * and square baseline, 2px surface gaps, hairline solid gridlines, no legend (title names the
 * series), hover tooltip with a hit target wider than the mark, and an sr-only table view.
 */
const props = defineProps({
  series: { type: Array, required: true }, // [{ date, total, orders }]
  title: { type: String, required: true },
})

const { t } = useI18n()
const { formatCurrency, formatNumber, formatDate } = useFormat()
const hovered = ref(null)

const W = 720
const H = 240
const PAD = { top: 12, right: 8, bottom: 28, left: 56 }
const innerW = W - PAD.left - PAD.right
const innerH = H - PAD.top - PAD.bottom

const values = computed(() => props.series.map((d) => Number(d.total) || 0))
const niceMax = computed(() => {
  const max = Math.max(...values.value, 0)
  if (max === 0) return 1000
  const magnitude = 10 ** Math.floor(Math.log10(max))
  const step = [1, 2, 2.5, 5, 10].find((s) => (s * magnitude * 4) >= max) * magnitude
  return step * 4
})
const ticks = computed(() => [0, 1, 2, 3, 4].map((i) => (niceMax.value / 4) * i))

const band = computed(() => innerW / Math.max(1, props.series.length))
const barWidth = computed(() => Math.min(24, Math.max(2, band.value - 2)))

function compact(value) {
  if (value >= 10000000) return `${formatNumber(value / 10000000, 1)}Cr`
  if (value >= 100000) return `${formatNumber(value / 100000, 1)}L`
  if (value >= 1000) return `${formatNumber(value / 1000, 0)}K`
  return formatNumber(value)
}

function barPath(i) {
  const v = values.value[i]
  if (v <= 0) return ''
  const h = Math.max(2, (v / niceMax.value) * innerH)
  const x = PAD.left + i * band.value + (band.value - barWidth.value) / 2
  const y = PAD.top + innerH - h
  const r = Math.min(4, barWidth.value / 2, h)
  const w = barWidth.value
  // rounded data-end (top), square at the baseline
  return `M${x},${y + h} V${y + r} Q${x},${y} ${x + r},${y} H${x + w - r} Q${x + w},${y} ${x + w},${y + r} V${y + h} Z`
}

const total = computed(() => values.value.reduce((a, b) => a + b, 0))
const tooltip = computed(() => {
  if (hovered.value === null) return null
  const d = props.series[hovered.value]
  const xPct = ((PAD.left + (hovered.value + 0.5) * band.value) / W) * 100
  return { d, xPct }
})
</script>

<template>
  <figure class="relative">
    <figcaption class="mb-1 flex items-baseline justify-between gap-2">
      <span class="text-sm font-semibold text-ink">{{ title }}</span>
      <span class="text-sm text-muted tabular">{{ formatCurrency(total) }}</span>
    </figcaption>

    <svg :viewBox="`0 0 ${W} ${H}`" class="h-auto w-full" role="img" :aria-label="`${title}: ${formatCurrency(total)}`" @mouseleave="hovered = null">
      <g>
        <template v-for="tick in ticks" :key="tick">
          <line :x1="PAD.left" :x2="W - PAD.right" :y1="PAD.top + innerH - (tick / niceMax) * innerH" :y2="PAD.top + innerH - (tick / niceMax) * innerH" stroke="#e2e8f0" stroke-width="1" />
          <text :x="PAD.left - 8" :y="PAD.top + innerH - (tick / niceMax) * innerH + 4" text-anchor="end" class="fill-slate-500 text-[11px] tabular">{{ compact(tick) }}</text>
        </template>
      </g>

      <g>
        <path v-for="(d, i) in series" :key="d.date" :d="barPath(i)" :fill="hovered === null || hovered === i ? '#0d9488' : '#99d5cf'" />
      </g>

      <g>
        <text v-for="i in [0, 7, 14, 21, 29].filter((n) => n < series.length)" :key="`x${i}`" :x="PAD.left + (i + 0.5) * band" :y="H - 8" text-anchor="middle" class="fill-slate-500 text-[11px]">
          {{ formatDate(series[i].date, { day: 'numeric', month: 'short' }) }}
        </text>
      </g>

      <!-- Hit targets: the full band height/width, larger than the mark itself -->
      <rect
        v-for="(d, i) in series"
        :key="`hit${d.date}`"
        :x="PAD.left + i * band"
        :y="PAD.top"
        :width="band"
        :height="innerH"
        fill="transparent"
        @mouseenter="hovered = i"
        @focus="hovered = i"
        @blur="hovered = null"
        tabindex="0"
        :aria-label="`${formatDate(d.date)}: ${formatCurrency(d.total)}`"
      />
    </svg>

    <div v-if="tooltip" class="pointer-events-none absolute top-6 z-10 -translate-x-1/2 rounded-lg border border-line bg-white px-3 py-2 text-xs whitespace-nowrap shadow-lg" :style="{ left: `${Math.min(88, Math.max(12, tooltip.xPct))}%` }">
      <p class="font-semibold text-ink">{{ formatDate(tooltip.d.date) }}</p>
      <p class="tabular text-ink">{{ formatCurrency(tooltip.d.total) }}</p>
      <p class="text-muted">{{ t('dashboard.orders_count', { count: formatNumber(tooltip.d.orders) }, tooltip.d.orders) }}</p>
    </div>

    <table class="sr-only">
      <caption>{{ title }}</caption>
      <thead><tr><th scope="col">{{ t('common.date') }}</th><th scope="col">{{ t('common.amount') }}</th><th scope="col">{{ t('nav.orders') }}</th></tr></thead>
      <tbody>
        <tr v-for="d in series" :key="`t${d.date}`"><td>{{ formatDate(d.date) }}</td><td>{{ formatCurrency(d.total) }}</td><td>{{ formatNumber(d.orders) }}</td></tr>
      </tbody>
    </table>
  </figure>
</template>
