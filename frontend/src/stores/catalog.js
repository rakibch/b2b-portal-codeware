import { defineStore } from 'pinia'
import { computed, reactive, ref } from 'vue'
import { catalogApi } from '@/api/catalog'
import { local } from '@/utils/storage'

const VIEW_KEY = 'b2b.catalog.view'

const DEFAULT_FILTERS = Object.freeze({
  q: '',
  category_id: null,
  brand_ids: [],
  in_stock: false,
  featured: false,
  sort: 'name',
  page: 1,
  per_page: 24,
})

export const useCatalogStore = defineStore('catalog', () => {
  const categories = ref([])
  const brands = ref([])
  const lookupsLoadedFor = ref(null)

  const products = ref([])
  const meta = ref({ current_page: 1, last_page: 1, total: 0, from: 0, to: 0 })
  const loading = ref(false)
  const error = ref(null)
  const filters = reactive({ ...DEFAULT_FILTERS, brand_ids: [] })
  const viewMode = ref(local.get(VIEW_KEY, 'grid') === 'list' ? 'list' : 'grid')

  let controller = null

  const activeFilterCount = computed(
    () => [filters.q, filters.category_id, filters.brand_ids.length, filters.in_stock, filters.featured].filter(Boolean).length,
  )

  /** Categories/brands are localised server-side, so reload when the locale changes. */
  async function loadLookups(locale) {
    if (lookupsLoadedFor.value === locale) return
    const [c, b] = await Promise.all([catalogApi.categories(), catalogApi.brands()])
    categories.value = c
    brands.value = b
    lookupsLoadedFor.value = locale
  }

  function queryParams() {
    const params = { page: filters.page, per_page: filters.per_page, sort: filters.sort }
    if (filters.q.trim()) params.q = filters.q.trim()
    if (filters.category_id) params.category_id = filters.category_id
    if (filters.brand_ids.length) params.brand_ids = filters.brand_ids
    if (filters.in_stock) params.in_stock = 1
    if (filters.featured) params.featured = 1
    return params
  }

  /** Fetches the current page; any in-flight request is aborted (fast typing in live search). */
  async function fetchProducts() {
    controller?.abort()
    controller = new AbortController()
    loading.value = true
    error.value = null
    try {
      const response = await catalogApi.products(queryParams(), controller.signal)
      products.value = response.data
      meta.value = response.meta
    } catch (e) {
      if (e?.name !== 'CanceledError' && e?.code !== 'ERR_CANCELED') error.value = e
    } finally {
      loading.value = false
    }
  }

  function setFilter(key, value) {
    filters[key] = value
    if (key !== 'page') filters.page = 1
  }

  function resetFilters() {
    Object.assign(filters, { ...DEFAULT_FILTERS, brand_ids: [] })
  }

  function setViewMode(mode) {
    viewMode.value = mode === 'list' ? 'list' : 'grid'
    local.set(VIEW_KEY, viewMode.value)
  }

  /** Replaces a product's pricing after a live re-quote (tier selector). */
  function patchProduct(id, patch) {
    const index = products.value.findIndex((p) => p.id === id)
    if (index !== -1) products.value[index] = { ...products.value[index], ...patch }
  }

  return {
    categories,
    brands,
    products,
    meta,
    loading,
    error,
    filters,
    viewMode,
    activeFilterCount,
    loadLookups,
    fetchProducts,
    setFilter,
    resetFilters,
    setViewMode,
    patchProduct,
    queryParams,
  }
})
