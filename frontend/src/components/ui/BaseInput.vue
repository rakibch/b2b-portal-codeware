<script setup>
defineOptions({ inheritAttrs: false })
import { computed, ref, useId } from 'vue'
import { useI18n } from 'vue-i18n'
import AppIcon from './AppIcon.vue'

const model = defineModel({ type: [String, Number, null], default: '' })

const props = defineProps({
  label: { type: String, default: null },
  type: { type: String, default: 'text' },
  error: { type: String, default: null },
  hint: { type: String, default: null },
  required: { type: Boolean, default: false },
  icon: { type: String, default: null },
  id: { type: String, default: null },
})

const { t } = useI18n()
const generatedId = useId()
const inputId = computed(() => props.id ?? generatedId)
const reveal = ref(false)
const actualType = computed(() => (props.type === 'password' && reveal.value ? 'text' : props.type))
</script>

<template>
  <div>
    <label v-if="label" :for="inputId" class="label">
      {{ label }}<span v-if="required" class="text-red-500" aria-hidden="true"> *</span>
    </label>
    <div class="relative">
      <AppIcon v-if="icon" :name="icon" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
      <input
        :id="inputId"
        v-model="model"
        v-bind="$attrs"
        :type="actualType"
        :required="required"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined"
        class="input"
        :class="[icon ? 'pl-9' : '', type === 'password' ? 'pr-10' : '', error ? 'border-red-400 focus:border-red-500 focus:ring-red-500/20' : '']"
      />
      <button
        v-if="type === 'password'"
        type="button"
        class="absolute top-1/2 right-2 -translate-y-1/2 rounded p-1 text-slate-400 hover:text-slate-600"
        :aria-label="reveal ? t('auth.hide_password') : t('auth.show_password')"
        @click="reveal = !reveal"
      >
        <AppIcon :name="reveal ? 'EyeOff' : 'Eye'" :size="16" />
      </button>
    </div>
    <p v-if="error" :id="`${inputId}-error`" class="field-error" role="alert">{{ error }}</p>
    <p v-else-if="hint" :id="`${inputId}-hint`" class="mt-1 text-xs text-muted">{{ hint }}</p>
  </div>
</template>
