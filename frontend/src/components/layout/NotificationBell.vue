<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useFormat } from '@/composables/useFormat'
import { useNotificationStore } from '@/stores/notifications'

const store = useNotificationStore()
const router = useRouter()
const { t } = useI18n()
const { formatRelative, formatNumber } = useFormat()
const open = ref(false)
const root = ref(null)

async function toggle() {
  open.value = !open.value
  if (open.value) await store.load()
}

async function openNotification(notification) {
  await store.markRead(notification)
  open.value = false
  // Only follow in-app paths from the server (never external URLs).
  if (notification.path?.startsWith('/') && !notification.path.startsWith('//')) {
    router.push(notification.path).catch(() => {})
  }
}

function onClickOutside(event) {
  if (open.value && root.value && !root.value.contains(event.target)) open.value = false
}

function onKeydown(event) {
  if (event.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('mousedown', onClickOutside)
  document.addEventListener('keydown', onKeydown)
})
onBeforeUnmount(() => {
  document.removeEventListener('mousedown', onClickOutside)
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus-visible:ring-2 focus-visible:ring-brand-600/40 focus-visible:outline-none"
      :aria-label="t('nav.notifications')"
      :aria-expanded="open"
      @click="toggle"
    >
      <AppIcon name="Bell" :size="20" />
      <span
        v-if="store.unreadCount > 0"
        class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white"
      >
        {{ store.unreadCount > 99 ? '99+' : formatNumber(store.unreadCount) }}
      </span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 z-40 mt-2 w-[22rem] max-w-[calc(100vw-1.5rem)] overflow-hidden rounded-xl border border-line bg-white shadow-xl"
      role="dialog"
      :aria-label="t('notifications.title')"
    >
      <div class="flex items-center justify-between border-b border-line px-4 py-3">
        <p class="text-sm font-semibold">{{ t('notifications.title') }}</p>
        <button v-if="store.unreadCount" type="button" class="text-xs font-medium text-brand-700 hover:underline" @click="store.markAllRead()">
          {{ t('notifications.mark_all_read') }}
        </button>
      </div>
      <ul class="max-h-96 divide-y divide-line overflow-y-auto">
        <li v-if="!store.items.length && !store.loading" class="px-4 py-8 text-center text-sm text-muted">{{ t('notifications.empty') }}</li>
        <li v-for="n in store.items" :key="n.id">
          <button type="button" class="flex w-full gap-3 px-4 py-3 text-left hover:bg-slate-50" @click="openNotification(n)">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="n.read_at ? 'bg-transparent' : 'bg-brand-600'" aria-hidden="true" />
            <span class="min-w-0">
              <span class="block text-sm font-medium text-ink">{{ n.title }}</span>
              <span class="mt-0.5 line-clamp-2 block text-xs text-muted">{{ n.body }}</span>
              <span class="mt-1 block text-[11px] text-slate-400">{{ formatRelative(n.created_at) }}</span>
            </span>
          </button>
        </li>
      </ul>
      <button v-if="store.hasMore" type="button" class="w-full border-t border-line py-2 text-xs font-medium text-brand-700 hover:bg-slate-50" :disabled="store.loading" @click="store.load({ reset: false })">
        {{ t('notifications.load_more') }}
      </button>
    </div>
  </div>
</template>
