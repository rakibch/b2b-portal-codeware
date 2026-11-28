import { defineStore } from 'pinia'
import { ref } from 'vue'

let nextId = 1

export const useToastStore = defineStore('toast', () => {
  const toasts = ref([])

  function push(type, message, { timeout = 4500, title = null } = {}) {
    if (!message) return null
    const id = nextId++
    // De-duplicate identical messages already on screen (e.g. repeated network errors).
    if (toasts.value.some((t) => t.message === message && t.type === type)) return null
    toasts.value.push({ id, type, title, message })
    if (timeout > 0) setTimeout(() => dismiss(id), timeout)
    return id
  }

  function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return {
    toasts,
    dismiss,
    success: (message, options) => push('success', message, options),
    error: (message, options) => push('error', message, { timeout: 7000, ...options }),
    info: (message, options) => push('info', message, options),
    warning: (message, options) => push('warning', message, options),
  }
})
