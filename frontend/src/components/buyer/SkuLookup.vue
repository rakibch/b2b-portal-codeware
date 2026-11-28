<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { catalogApi } from '@/api/catalog'
import { debounce } from '@/composables/useDebounce'

/** Accessible combobox: type SKU / name (EN or BN) → pick a product. */
const model = defineModel({ type: String, default: '' })
const emit = defineEmits(['select'])
defineProps({ invalid: { type: Boolean, default: false } })

const { t } = useI18n()
const results = ref([])
const open = ref(false)
const active = ref(-1)
let controller = null
let skipNextSearch = false

const search = debounce(async (q) => {
  if (q.trim().length < 2) {
    results.value = []
    return
  }
  controller?.abort()
  controller = new AbortController()
  try {
    results.value = await catalogApi.lookup(q.trim(), controller.signal)
    open.value = results.value.length > 0
    active.value = -1
  } catch {
    /* aborted or transient */
  }
}, 200)

watch(model, (q) => {
  // A value set by choosing a suggestion must not trigger a new search / reopen the list.
  if (skipNextSearch) {
    skipNextSearch = false
    return
  }
  search(q ?? '')
})

function choose(product) {
  skipNextSearch = model.value !== product.sku
  search.cancel()
  results.value = []
  model.value = product.sku
  open.value = false
  emit('select', product)
}

function closeSoon() {
  setTimeout(() => {
    open.value = false
  }, 150)
}

function onKeydown(event) {
  if (!open.value) return
  if (event.key === 'ArrowDown') {
    active.value = Math.min(results.value.length - 1, active.value + 1)
    event.preventDefault()
  } else if (event.key === 'ArrowUp') {
    active.value = Math.max(0, active.value - 1)
    event.preventDefault()
  } else if (event.key === 'Enter' && active.value >= 0) {
    choose(results.value[active.value])
    event.preventDefault()
  } else if (event.key === 'Escape') {
    open.value = false
  }
}

onBeforeUnmount(() => {
  search.cancel()
  controller?.abort()
})
</script>

<template>
  <div class="relative">
    <input
      v-model="model"
      type="text"
      class="input font-mono uppercase placeholder:normal-case"
      :class="invalid ? 'border-red-400' : ''"
      :placeholder="t('quick_order.sku_or_name')"
      role="combobox"
      :aria-expanded="open"
      aria-autocomplete="list"
      autocomplete="off"
      @keydown="onKeydown"
      @focus="open = results.length > 0"
      @blur="closeSoon"
    />
    <ul v-if="open" role="listbox" class="absolute z-30 mt-1 max-h-64 w-full min-w-[18rem] overflow-y-auto rounded-lg border border-line bg-white py-1 shadow-lg">
      <li
        v-for="(p, i) in results"
        :key="p.id"
        role="option"
        :aria-selected="i === active"
        class="cursor-pointer px-3 py-2 text-sm"
        :class="i === active ? 'bg-brand-50' : 'hover:bg-slate-50'"
        @mousedown.prevent="choose(p)"
      >
        <span class="font-mono text-xs text-muted">{{ p.sku }}</span>
        <span class="ml-2 text-ink">{{ p.name }}</span>
      </li>
    </ul>
  </div>
</template>
