<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'

const open = defineModel('open', { type: Boolean, default: false })

const props = defineProps({
  title: { type: String, default: null },
  size: { type: String, default: 'md' }, // sm | md | lg | xl
  dismissible: { type: Boolean, default: true },
})

const { t } = useI18n()
const panel = ref(null)
let previouslyFocused = null

const widths = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl' }

function close() {
  if (props.dismissible) open.value = false
}

function onKeydown(event) {
  if (event.key === 'Escape') close()
  if (event.key === 'Tab' && panel.value) {
    // Minimal focus trap: keep Tab cycling inside the dialog.
    const focusable = panel.value.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])')
    if (!focusable.length) return
    const first = focusable[0]
    const last = focusable[focusable.length - 1]
    if (event.shiftKey && document.activeElement === first) {
      last.focus()
      event.preventDefault()
    } else if (!event.shiftKey && document.activeElement === last) {
      first.focus()
      event.preventDefault()
    }
  }
}

watch(open, async (value) => {
  if (value) {
    previouslyFocused = document.activeElement
    document.body.style.overflow = 'hidden'
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    panel.value?.focus()
  } else {
    document.body.style.overflow = ''
    document.removeEventListener('keydown', onKeydown)
    previouslyFocused?.focus?.()
  }
})

onBeforeUnmount(() => {
  document.body.style.overflow = ''
  document.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" enter-active-class="transition duration-150" leave-active-class="transition duration-100">
      <div v-if="open" class="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/40 p-0 sm:items-center sm:p-4" @mousedown.self="close">
        <div
          ref="panel"
          role="dialog"
          aria-modal="true"
          :aria-label="title ?? undefined"
          tabindex="-1"
          class="max-h-[92vh] w-full overflow-y-auto rounded-t-2xl bg-white shadow-xl outline-none sm:rounded-2xl"
          :class="widths[size] ?? widths.md"
        >
          <div v-if="title || dismissible" class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
            <h2 class="text-base font-semibold text-ink">{{ title }}</h2>
            <button v-if="dismissible" type="button" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" :aria-label="t('common.close')" @click="close">
              <AppIcon name="X" :size="18" />
            </button>
          </div>
          <div class="px-5 py-4"><slot /></div>
          <div v-if="$slots.footer" class="flex flex-col-reverse gap-2 border-t border-line bg-slate-50 px-5 py-3 sm:flex-row sm:justify-end">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
