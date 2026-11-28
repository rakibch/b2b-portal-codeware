<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { useToastStore } from '@/stores/toast'

const store = useToastStore()
const { t } = useI18n()

const STYLES = {
  success: { icon: 'CheckCircle2', cls: 'text-emerald-600' },
  error: { icon: 'XCircle', cls: 'text-red-600' },
  warning: { icon: 'AlertTriangle', cls: 'text-amber-600' },
  info: { icon: 'Info', cls: 'text-sky-600' },
}
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4 sm:top-0 sm:bottom-auto sm:items-end" aria-live="polite" aria-atomic="false">
    <TransitionGroup enter-from-class="translate-y-2 opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-200" leave-active-class="transition duration-150">
      <div
        v-for="toast in store.toasts"
        :key="toast.id"
        :role="toast.type === 'error' ? 'alert' : 'status'"
        class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-line bg-white p-4 shadow-lg"
      >
        <AppIcon :name="STYLES[toast.type].icon" :size="20" :class="STYLES[toast.type].cls" class="mt-0.5 shrink-0" />
        <div class="min-w-0 flex-1 text-sm">
          <p v-if="toast.title" class="font-semibold text-ink">{{ toast.title }}</p>
          <p class="text-slate-600">{{ toast.message }}</p>
        </div>
        <button type="button" class="rounded p-0.5 text-slate-400 hover:text-slate-600" :aria-label="t('common.close')" @click="store.dismiss(toast.id)">
          <AppIcon name="X" :size="16" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
