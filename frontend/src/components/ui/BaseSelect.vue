<script setup>
defineOptions({ inheritAttrs: false })
import { computed, useId } from 'vue'

const model = defineModel({ type: [String, Number, null], default: null })

const props = defineProps({
  label: { type: String, default: null },
  options: { type: Array, default: () => [] }, // [{ value, label }]
  placeholder: { type: String, default: null },
  error: { type: String, default: null },
  required: { type: Boolean, default: false },
  id: { type: String, default: null },
})

const generatedId = useId()
const selectId = computed(() => props.id ?? generatedId)
</script>

<template>
  <div>
    <label v-if="label" :for="selectId" class="label">
      {{ label }}<span v-if="required" class="text-red-500" aria-hidden="true"> *</span>
    </label>
    <select
      :id="selectId"
      v-model="model"
      v-bind="$attrs"
      :required="required"
      :aria-invalid="Boolean(error)"
      class="input pr-8"
      :class="error ? 'border-red-400' : ''"
    >
      <option v-if="placeholder" :value="null" disabled>{{ placeholder }}</option>
      <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
    </select>
    <p v-if="error" class="field-error" role="alert">{{ error }}</p>
  </div>
</template>
