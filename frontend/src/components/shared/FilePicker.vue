<script setup>
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'

const model = defineModel({ type: [File, null], default: null })
const props = defineProps({
  label: { type: String, required: true },
  accept: { type: String, default: '.pdf,.jpg,.jpeg,.png' },
  maxMb: { type: Number, default: 5 },
  required: { type: Boolean, default: false },
  error: { type: String, default: null },
})

const { t } = useI18n()
const id = useId()
const localError = ref(null)
const shownError = computed(() => localError.value ?? props.error)

function onChange(event) {
  const file = event.target.files?.[0] ?? null
  localError.value = null
  if (!file) return
  const allowed = props.accept.split(',').map((s) => s.trim().toLowerCase())
  const ext = `.${file.name.split('.').pop()?.toLowerCase()}`
  if (!allowed.includes(ext)) {
    localError.value = t('validation.file_type', { types: props.accept })
    event.target.value = ''
    return
  }
  if (file.size > props.maxMb * 1024 * 1024) {
    localError.value = t('validation.file_too_large', { size: props.maxMb })
    event.target.value = ''
    return
  }
  model.value = file
}
</script>

<template>
  <div>
    <span class="label">{{ label }}<span v-if="required" class="text-red-500" aria-hidden="true"> *</span></span>
    <label :for="id" class="flex cursor-pointer items-center gap-3 rounded-lg border border-dashed px-4 py-3 text-sm hover:bg-slate-50" :class="shownError ? 'border-red-400' : 'border-slate-300'">
      <AppIcon :name="model ? 'CheckCircle2' : 'Upload'" :size="18" :class="model ? 'text-emerald-600' : 'text-slate-400'" />
      <span class="min-w-0 flex-1 truncate" :class="model ? 'text-ink' : 'text-muted'">{{ model ? model.name : t('register.choose_file') }}</span>
      <input :id="id" type="file" class="sr-only" :accept="accept" :required="required && !model" @change="onChange" />
    </label>
    <p v-if="shownError" class="field-error" role="alert">{{ shownError }}</p>
  </div>
</template>
