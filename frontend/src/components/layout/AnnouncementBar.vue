<script setup>
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useNotificationStore } from '@/stores/notifications'
import { session } from '@/utils/storage'

const store = useNotificationStore()
const { t } = useI18n()
const DISMISS_KEY = 'b2b.dismissed_announcements'
const dismissed = ref(new Set(JSON.parse(session.get(DISMISS_KEY, '[]'))))

const visible = computed(() => store.announcements.filter((a) => !dismissed.value.has(a.id)))

const TONES = {
  info: 'bg-sky-50 text-sky-900 border-sky-200',
  success: 'bg-emerald-50 text-emerald-900 border-emerald-200',
  warning: 'bg-amber-50 text-amber-900 border-amber-200',
  danger: 'bg-red-50 text-red-900 border-red-200',
}

function dismiss(id) {
  dismissed.value = new Set([...dismissed.value, id])
  session.set(DISMISS_KEY, JSON.stringify([...dismissed.value]))
}
</script>

<template>
  <div v-if="visible.length" class="space-y-2 px-4 pt-4 sm:px-6">
    <div v-for="a in visible" :key="a.id" class="flex items-start gap-3 rounded-lg border px-4 py-3 text-sm" :class="TONES[a.level] ?? TONES.info" role="status">
      <AppIcon name="Megaphone" :size="18" class="mt-0.5 shrink-0" />
      <p class="min-w-0 flex-1"><span class="font-semibold">{{ a.title }}</span> — {{ a.body }}</p>
      <button type="button" class="shrink-0 rounded p-0.5 opacity-60 hover:opacity-100" :aria-label="t('common.close')" @click="dismiss(a.id)">
        <AppIcon name="X" :size="16" />
      </button>
    </div>
  </div>
</template>
