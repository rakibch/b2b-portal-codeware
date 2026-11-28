import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { cartApi } from '@/api/cart'

const EMPTY = {
  items: [],
  totals: { subtotal: '0.00', discount_total: '0.00', vat_total: '0.00', grand_total: '0.00' },
  item_count: 0,
  has_errors: false,
}

export const useCartStore = defineStore('cart', () => {
  const cart = ref({ ...EMPTY })
  const loading = ref(false)
  const loaded = ref(false)
  const busyItemIds = ref(new Set())

  const items = computed(() => cart.value.items)
  const count = computed(() => cart.value.item_count)
  const totals = computed(() => cart.value.totals)
  const hasErrors = computed(() => cart.value.has_errors)
  const isEmpty = computed(() => cart.value.item_count === 0)

  function apply(data) {
    cart.value = data ?? { ...EMPTY }
    loaded.value = true
    return cart.value
  }

  async function fetch({ force = false } = {}) {
    if (loaded.value && !force) return cart.value
    loading.value = true
    try {
      return apply(await cartApi.show())
    } finally {
      loading.value = false
    }
  }

  /** @param {Array<{product_id:number, quantity:number}>} lines */
  async function add(lines, source = 'catalog', mode = 'merge') {
    return apply(await cartApi.add(lines, source, mode))
  }

  async function updateQuantity(itemId, quantity) {
    busyItemIds.value.add(itemId)
    try {
      return apply(await cartApi.update(itemId, quantity))
    } finally {
      busyItemIds.value.delete(itemId)
    }
  }

  async function remove(itemId) {
    busyItemIds.value.add(itemId)
    try {
      return apply(await cartApi.remove(itemId))
    } finally {
      busyItemIds.value.delete(itemId)
    }
  }

  async function clear() {
    return apply(await cartApi.clear())
  }

  /** Used after checkout / logout. */
  function reset() {
    cart.value = { ...EMPTY }
    loaded.value = false
  }

  return { cart, loading, loaded, busyItemIds, items, count, totals, hasErrors, isEmpty, apply, fetch, add, updateQuantity, remove, clear, reset }
})
