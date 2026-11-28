<script setup>
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useFormat } from '@/composables/useFormat'

const props = defineProps({ meta: { type: Object, default: null } })
const emit = defineEmits(['page'])
const { t } = useI18n()
const { formatNumber } = useFormat()

const show = computed(() => props.meta && props.meta.last_page > 1)
</script>

<template>
  <div v-if="meta && meta.total" class="flex flex-col items-center justify-between gap-3 px-4 py-3 text-sm text-muted sm:flex-row">
    <p>{{ t('common.showing_range', { from: formatNumber(meta.from ?? 0), to: formatNumber(meta.to ?? 0), total: formatNumber(meta.total) }) }}</p>
    <div v-if="show" class="flex items-center gap-2">
      <button type="button" class="rounded-lg border border-line bg-white p-1.5 hover:bg-slate-50 disabled:opacity-40" :disabled="meta.current_page <= 1" :aria-label="t('common.previous')" @click="emit('page', meta.current_page - 1)">
        <AppIcon name="ChevronLeft" :size="16" />
      </button>
      <span class="tabular">{{ t('common.page_x_of_y', { page: formatNumber(meta.current_page), last: formatNumber(meta.last_page) }) }}</span>
      <button type="button" class="rounded-lg border border-line bg-white p-1.5 hover:bg-slate-50 disabled:opacity-40" :disabled="meta.current_page >= meta.last_page" :aria-label="t('common.next')" @click="emit('page', meta.current_page + 1)">
        <AppIcon name="ChevronRight" :size="16" />
      </button>
    </div>
  </div>
</template>
