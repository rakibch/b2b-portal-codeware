<script setup>
import { useI18n } from 'vue-i18n'
import AppIcon from '@/components/ui/AppIcon.vue'
import { nextValidQuantity } from '@/utils/quantity'

const model = defineModel({ type: Number, required: true })
const props = defineProps({
  product: { type: Object, required: true }, // { moq, step_quantity, max_order_quantity }
  disabled: { type: Boolean, default: false },
  size: { type: String, default: 'md' },
})

const { t } = useI18n()

function step(direction) {
  model.value = nextValidQuantity(props.product, model.value, direction)
}

function onBlur(event) {
  const value = Number.parseInt(event.target.value, 10)
  model.value = nextValidQuantity(props.product, Number.isNaN(value) ? props.product.moq : value, 0)
}
</script>

<template>
  <div class="inline-flex items-stretch overflow-hidden rounded-lg border border-line bg-white shadow-sm" :class="size === 'sm' ? 'h-8' : 'h-10'">
    <button type="button" class="px-2.5 text-slate-500 hover:bg-slate-50 disabled:opacity-40" :disabled="disabled || model <= product.moq" :aria-label="t('common.less')" @click="step(-1)">
      <AppIcon name="Minus" :size="14" />
    </button>
    <input
      :value="model"
      type="number"
      inputmode="numeric"
      :min="product.moq"
      :step="product.step_quantity"
      :disabled="disabled"
      class="w-20 border-x border-line text-center text-sm tabular focus:outline-none [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
      :aria-label="t('common.quantity')"
      @change="onBlur"
      @keydown.enter.prevent="onBlur"
    />
    <button type="button" class="px-2.5 text-slate-500 hover:bg-slate-50 disabled:opacity-40" :disabled="disabled" :aria-label="t('common.more')" @click="step(1)">
      <AppIcon name="Plus" :size="14" />
    </button>
  </div>
</template>
