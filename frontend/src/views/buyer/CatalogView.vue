<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import PageHeader from '@/components/ui/PageHeader.vue'
import SpinnerBlock from '@/components/ui/SpinnerBlock.vue'
import PaginationBar from '@/components/shared/PaginationBar.vue'
import ProductCard from '@/components/buyer/ProductCard.vue'
import { debounce } from '@/composables/useDebounce'
import { useFormat } from '@/composables/useFormat'
import { useAuthStore } from '@/stores/auth'
import { useCatalogStore } from '@/stores/catalog'

const { t, locale } = useI18n()
const { formatNumber } = useFormat()
const store = useCatalogStore()
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const showFilters = ref(false)
const search = ref(store.filters.q)

const sortOptions = computed(() => [
  { value: 'name', label: t('catalog.sort_name') },
  { value: 'relevance', label: t('catalog.sort_relevance') },
  { value: 'newest', label: t('catalog.sort_newest') },
  { value: 'price_asc', label: t('catalog.sort_price_asc') },
  { value: 'price_desc', label: t('catalog.sort_price_desc') },
  { value: 'stock', label: t('catalog.sort_stock') },
])

/** URL ⇄ filters keeps catalog state shareable and back-button friendly. */
function readQuery() {
  const q = route.query
  store.filters.q = typeof q.q === 'string' ? q.q : ''
  store.filters.category_id = q.category ? Number(q.category) : null
  store.filters.brand_ids = [].concat(q.brand ?? []).map(Number).filter(Boolean)
  store.filters.in_stock = q.in_stock === '1'
  store.filters.featured = q.featured === '1'
  store.filters.sort = sortOptions.value.some((o) => o.value === q.sort) ? q.sort : store.filters.q ? 'relevance' : 'name'
  store.filters.page = Number(q.page) > 0 ? Number(q.page) : 1
  search.value = store.filters.q
}

function writeQuery() {
  const f = store.filters
  router.replace({
    query: {
      ...(f.q ? { q: f.q } : {}),
      ...(f.category_id ? { category: String(f.category_id) } : {}),
      ...(f.brand_ids.length ? { brand: f.brand_ids.map(String) } : {}),
      ...(f.in_stock ? { in_stock: '1' } : {}),
      ...(f.featured ? { featured: '1' } : {}),
      ...(f.sort !== 'name' ? { sort: f.sort } : {}),
      ...(f.page > 1 ? { page: String(f.page) } : {}),
    },
  })
}

function update(key, value) {
  store.setFilter(key, value)
  writeQuery()
}

const onSearch = debounce((value) => {
  store.setFilter('q', value.trim())
  if (value.trim() && store.filters.sort === 'name') store.filters.sort = 'relevance'
  writeQuery()
}, 300)

watch(search, (value) => onSearch(value))
watch(() => route.query, () => {
  readQuery()
  store.fetchProducts()
}, { deep: true })

watch(locale, async (value) => {
  await store.loadLookups(value)
  store.fetchProducts()
})

function toggleBrand(id) {
  const set = new Set(store.filters.brand_ids)
  set.has(id) ? set.delete(id) : set.add(id)
  update('brand_ids', [...set])
}

function clearAll() {
  store.resetFilters()
  search.value = ''
  writeQuery()
}

onMounted(async () => {
  readQuery()
  await Promise.all([store.loadLookups(locale.value), store.fetchProducts()])
})
</script>

<template>
  <div>
    <PageHeader :title="t('catalog.title')" :subtitle="store.meta.total ? t('catalog.products_found', { count: formatNumber(store.meta.total) }) : null">
      <template #actions>
        <div class="inline-flex rounded-lg border border-line bg-white p-0.5 shadow-sm">
          <button type="button" class="rounded-md p-1.5" :class="store.viewMode === 'grid' ? 'bg-brand-700 text-white' : 'text-slate-500 hover:bg-slate-100'" :aria-label="t('catalog.grid_view')" :aria-pressed="store.viewMode === 'grid'" @click="store.setViewMode('grid')">
            <AppIcon name="LayoutGrid" :size="16" />
          </button>
          <button type="button" class="rounded-md p-1.5" :class="store.viewMode === 'list' ? 'bg-brand-700 text-white' : 'text-slate-500 hover:bg-slate-100'" :aria-label="t('catalog.list_view')" :aria-pressed="store.viewMode === 'list'" @click="store.setViewMode('list')">
            <AppIcon name="List" :size="16" />
          </button>
        </div>
        <BaseButton v-if="auth.canTransact && $router.hasRoute('buyer.quick-order')" variant="secondary" icon="Zap" :to="{ name: 'buyer.quick-order' }">{{ t('nav.quick_order') }}</BaseButton>
      </template>
    </PageHeader>

    <div class="mb-4 flex flex-col gap-3 sm:flex-row">
      <div class="relative flex-1">
        <AppIcon name="Search" :size="16" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400" />
        <input v-model="search" type="search" class="input pl-9" :placeholder="t('catalog.search_placeholder')" :aria-label="t('common.search')" />
      </div>
      <select class="input sm:w-56" :value="store.filters.sort" :aria-label="t('catalog.sort')" @change="update('sort', $event.target.value)">
        <option v-for="o in sortOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
      <BaseButton variant="secondary" class="lg:hidden" icon="List" @click="showFilters = !showFilters">
        {{ t('common.filters') }}<span v-if="store.activeFilterCount" class="ml-1 rounded-full bg-brand-100 px-1.5 text-xs text-brand-800">{{ formatNumber(store.activeFilterCount) }}</span>
      </BaseButton>
    </div>

    <div class="flex flex-col gap-6 lg:flex-row">
      <aside class="shrink-0 space-y-6 lg:block lg:w-60" :class="showFilters ? 'block' : 'hidden'">
        <section>
          <h2 class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">{{ t('catalog.categories') }}</h2>
          <ul class="space-y-0.5 text-sm">
            <li>
              <button type="button" class="w-full rounded-lg px-3 py-1.5 text-left" :class="!store.filters.category_id ? 'bg-brand-50 font-semibold text-brand-800' : 'text-slate-600 hover:bg-slate-100'" @click="update('category_id', null)">{{ t('catalog.all_categories') }}</button>
            </li>
            <li v-for="c in store.categories.filter((c) => !c.parent_id)" :key="c.id">
              <button type="button" class="flex w-full justify-between rounded-lg px-3 py-1.5 text-left" :class="store.filters.category_id === c.id ? 'bg-brand-50 font-semibold text-brand-800' : 'text-slate-600 hover:bg-slate-100'" @click="update('category_id', c.id)">
                <span class="truncate">{{ c.name }}</span><span class="text-xs text-slate-400 tabular">{{ formatNumber(c.products_count) }}</span>
              </button>
            </li>
          </ul>
        </section>
        <section v-if="store.brands.length">
          <h2 class="mb-2 text-xs font-semibold tracking-wide text-muted uppercase">{{ t('catalog.brands') }}</h2>
          <ul class="space-y-1.5 text-sm">
            <li v-for="b in store.brands" :key="b.id">
              <label class="flex items-center gap-2 text-slate-600">
                <input type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" :checked="store.filters.brand_ids.includes(b.id)" @change="toggleBrand(b.id)" />
                <span class="flex-1 truncate">{{ b.name }}</span>
              </label>
            </li>
          </ul>
        </section>
        <section class="space-y-2 text-sm">
          <label class="flex items-center gap-2 text-slate-600"><input type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" :checked="store.filters.in_stock" @change="update('in_stock', $event.target.checked)" />{{ t('catalog.in_stock_only') }}</label>
          <label class="flex items-center gap-2 text-slate-600"><input type="checkbox" class="h-4 w-4 rounded border-line text-brand-700" :checked="store.filters.featured" @change="update('featured', $event.target.checked)" />{{ t('catalog.featured_only') }}</label>
        </section>
        <BaseButton v-if="store.activeFilterCount" variant="link" @click="clearAll">{{ t('common.clear_filters') }}</BaseButton>
      </aside>

      <div class="min-w-0 flex-1">
        <div v-if="!auth.canTransact" class="mb-4 flex items-center gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          <AppIcon name="ShieldCheck" :size="18" /> {{ t('kyc.pending_body') }}
        </div>

        <SpinnerBlock v-if="store.loading && !store.products.length" />
        <div v-else-if="!store.products.length" class="card">
          <EmptyState icon="Search" :title="t('catalog.no_products')"><BaseButton variant="secondary" @click="clearAll">{{ t('common.clear_filters') }}</BaseButton></EmptyState>
        </div>
        <div v-else :class="[store.viewMode === 'grid' ? 'grid gap-4 sm:grid-cols-2 xl:grid-cols-3' : 'space-y-3', store.loading ? 'opacity-60 transition-opacity' : '']">
          <ProductCard v-for="p in store.products" :key="p.id" :product="p" :layout="store.viewMode" :can-buy="auth.canTransact" />
        </div>
        <PaginationBar :meta="store.meta" @page="update('page', $event)" />
      </div>
    </div>
  </div>
</template>
