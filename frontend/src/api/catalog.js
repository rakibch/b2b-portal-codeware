import { http } from './http'

export const catalogApi = {
  categories: () => http.get('/catalog/categories').then((r) => r.data.data),
  brands: () => http.get('/catalog/brands').then((r) => r.data.data),
  products: (params, signal) => http.get('/catalog/products', { params, signal }).then((r) => r.data),
  product: (slug) => http.get(`/catalog/products/${encodeURIComponent(slug)}`).then((r) => r.data.data),
  lookup: (q, signal) => http.get('/catalog/products/lookup', { params: { q }, signal, silent: true }).then((r) => r.data.data),
  quote: (lines, signal) => http.post('/catalog/price-quote', { lines }, { signal, silent: true }).then((r) => r.data),
}
