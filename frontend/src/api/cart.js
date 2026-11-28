import { http } from './http'

export const cartApi = {
  show: () => http.get('/buyer/cart').then((r) => r.data.data),
  add: (items, source = 'catalog', mode = 'merge') =>
    http.post('/buyer/cart/items', { items, source, mode }, { silent: true }).then((r) => r.data.data),
  update: (itemId, quantity) => http.patch(`/buyer/cart/items/${itemId}`, { quantity }, { silent: true }).then((r) => r.data.data),
  remove: (itemId) => http.delete(`/buyer/cart/items/${itemId}`).then((r) => r.data.data),
  clear: () => http.delete('/buyer/cart').then((r) => r.data.data),
}
