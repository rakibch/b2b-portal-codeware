import { onBeforeUnmount, ref, watch } from 'vue'
import { catalogApi } from '@/api/catalog'

/**
 * Live server-side price for a product at a quantity (tier / group / contract pricing).
 * Debounced and abortable; the server is the single source of truth for prices.
 */
export function useLiveQuote(productId, quantity, { delay = 250, initial = null } = {}) {
  const quote = ref(initial)
  const loading = ref(false)
  let timer = null
  let controller = null

  async function run() {
    const id = productId.value
    const qty = Number(quantity.value)
    if (!id || !Number.isInteger(qty) || qty < 0) return

    controller?.abort()
    controller = new AbortController()
    loading.value = true
    try {
      const response = await catalogApi.quote([{ product_id: id, quantity: qty }], controller.signal)
      quote.value = response.data[0] ?? null
    } catch (e) {
      if (e?.code !== 'ERR_CANCELED' && e?.name !== 'CanceledError') quote.value = null
    } finally {
      loading.value = false
    }
  }

  watch([productId, quantity], () => {
    clearTimeout(timer)
    timer = setTimeout(run, delay)
  })

  onBeforeUnmount(() => {
    clearTimeout(timer)
    controller?.abort()
  })

  return { quote, loading, refresh: run }
}
