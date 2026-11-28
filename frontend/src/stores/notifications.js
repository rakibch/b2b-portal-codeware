import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationsApi } from '@/api/notifications'

const POLL_MS = Math.max(15, Number(import.meta.env.VITE_NOTIFICATION_POLL_SECONDS || 60)) * 1000

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref([])
  const unreadCount = ref(0)
  const hasMore = ref(false)
  const page = ref(1)
  const loading = ref(false)
  const announcements = ref([])
  let timer = null

  async function refreshCount() {
    // Skip polling in background tabs to save server load.
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') return
    try {
      unreadCount.value = await notificationsApi.unreadCount()
    } catch {
      /* transient — next poll retries */
    }
  }

  async function load({ reset = true } = {}) {
    loading.value = true
    try {
      const next = reset ? 1 : page.value + 1
      const response = await notificationsApi.list({ page: next })
      items.value = reset ? response.data : [...items.value, ...response.data]
      unreadCount.value = response.unread_count
      hasMore.value = response.has_more
      page.value = next
    } finally {
      loading.value = false
    }
  }

  async function markRead(notification) {
    if (notification.read_at) return
    notification.read_at = new Date().toISOString()
    unreadCount.value = await notificationsApi.markRead(notification.id)
  }

  async function markAllRead() {
    unreadCount.value = await notificationsApi.markAllRead()
    const now = new Date().toISOString()
    items.value = items.value.map((n) => ({ ...n, read_at: n.read_at ?? now }))
  }

  async function loadAnnouncements() {
    try {
      announcements.value = await notificationsApi.announcements()
    } catch {
      announcements.value = []
    }
  }

  function startPolling() {
    stopPolling()
    refreshCount()
    timer = setInterval(refreshCount, POLL_MS)
  }

  function stopPolling() {
    if (timer) clearInterval(timer)
    timer = null
  }

  function reset() {
    stopPolling()
    items.value = []
    unreadCount.value = 0
    announcements.value = []
    page.value = 1
  }

  return { items, unreadCount, hasMore, loading, announcements, refreshCount, load, markRead, markAllRead, loadAnnouncements, startPolling, stopPolling, reset }
})
