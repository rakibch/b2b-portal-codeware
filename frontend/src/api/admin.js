import { download, http } from './http'

const data = (r) => r.data.data
const body = (r) => r.data
const ref = (v) => encodeURIComponent(v)

export const adminApi = {
  dashboard: () => http.get('/admin/dashboard').then(data),
  lookups: () => http.get('/admin/lookups').then(data),

  // KYC
  kycQueue: (params) => http.get('/admin/kyc', { params }).then(body),
  kycCompany: (id) => http.get(`/admin/kyc/${ref(id)}`).then(data),
  approveKyc: (id, payload) => http.post(`/admin/kyc/${ref(id)}/approve`, payload, { silent: true }).then(body),
  rejectKyc: (id, reason) => http.post(`/admin/kyc/${ref(id)}/reject`, { reason }, { silent: true }).then(body),
  suspendKyc: (id, reason) => http.post(`/admin/kyc/${ref(id)}/suspend`, { reason }, { silent: true }).then(body),
  reviewDocument: (companyId, documentId, payload) => http.patch(`/admin/kyc/${ref(companyId)}/documents/${documentId}`, payload, { silent: true }).then(data),
  downloadDocument: (id, name) => download(`/documents/${id}/download`, {}, name),

  // Companies
  companies: (params) => http.get('/admin/companies', { params }).then(body),
  company: (id) => http.get(`/admin/companies/${ref(id)}`).then(data),
  companyUsers: (id) => http.get(`/admin/companies/${ref(id)}/users`).then(data),
  updateTerms: (id, payload) => http.patch(`/admin/companies/${ref(id)}/terms`, payload, { silent: true }).then(body),
  companyLedger: (id, params) => http.get(`/admin/companies/${ref(id)}/ledger`, { params }).then(body),
  exportCompanyLedger: (id, params) => download(`/admin/companies/${ref(id)}/ledger/export`, params, 'statement.xlsx'),
  adjustLedger: (id, payload) => http.post(`/admin/companies/${ref(id)}/ledger/adjustments`, payload, { silent: true }).then(body),
  creditNote: (id, payload) => http.post(`/admin/companies/${ref(id)}/credit-notes`, payload, { silent: true }).then(body),
  creditNotes: (id) => http.get(`/admin/companies/${ref(id)}/credit-notes`).then(body),
  contractPrices: (id) => http.get(`/admin/companies/${ref(id)}/prices`).then(body),
  createContractPrice: (id, payload) => http.post(`/admin/companies/${ref(id)}/prices`, payload, { silent: true }).then(data),
  updateContractPrice: (id, priceId, payload) => http.patch(`/admin/companies/${ref(id)}/prices/${priceId}`, payload, { silent: true }).then(data),
  deleteContractPrice: (id, priceId) => http.delete(`/admin/companies/${ref(id)}/prices/${priceId}`).then(body),
  placeOrderFor: (id, payload) => http.post(`/admin/companies/${ref(id)}/orders`, payload, { silent: true }).then(body),

  // Orders & fulfilment
  orders: (params) => http.get('/admin/orders', { params }).then(body),
  order: (number) => http.get(`/admin/orders/${ref(number)}`).then(data),
  updateOrderStatus: (number, status, comment) => http.patch(`/admin/orders/${ref(number)}/status`, { status, comment }, { silent: true }).then(body),
  cancelOrder: (number, reason) => http.post(`/admin/orders/${ref(number)}/cancel`, { reason }, { silent: true }).then(body),
  shipments: (params) => http.get('/admin/shipments', { params }).then(body),
  createShipment: (number, payload) => http.post(`/admin/orders/${ref(number)}/shipments`, payload, { silent: true }).then(body),
  dispatchShipment: (challan, payload = {}) => http.post(`/admin/shipments/${ref(challan)}/dispatch`, payload, { silent: true }).then(body),
  deliverShipment: (challan, payload = {}) => http.post(`/admin/shipments/${ref(challan)}/deliver`, payload, { silent: true }).then(body),
  cancelShipment: (challan) => http.delete(`/admin/shipments/${ref(challan)}`).then(body),
  recordCash: (number, payload) => http.post(`/admin/orders/${ref(number)}/cash-payments`, payload, { silent: true }).then(body),
  invoicePdf: (number, lang) => download(`/invoices/${ref(number)}/pdf`, { lang }, `${number}-${lang}.pdf`),
  challanPdf: (challan, lang) => download(`/shipments/${ref(challan)}/challan`, { lang }, `${challan}-${lang}.pdf`),

  // Payments & receivables
  payments: (params) => http.get('/admin/payments', { params }).then(body),
  verifyPayment: (reference) => http.post(`/admin/payments/${ref(reference)}/verify`).then(body),
  rejectPayment: (reference, reason) => http.post(`/admin/payments/${ref(reference)}/reject`, { reason }, { silent: true }).then(body),
  paymentSlip: (reference) => download(`/payments/${ref(reference)}/slip`, {}, `${reference}-slip`),
  invoices: (params) => http.get('/admin/invoices', { params }).then(body),

  // RFQs
  rfqs: (params) => http.get('/admin/rfqs', { params }).then(body),
  rfq: (reference) => http.get(`/admin/rfqs/${ref(reference)}`).then(data),
  startRfqReview: (reference) => http.post(`/admin/rfqs/${ref(reference)}/review`).then(data),
  quoteRfq: (reference, payload) => http.post(`/admin/rfqs/${ref(reference)}/quote`, payload, { silent: true }).then(body),
  declineRfq: (reference, reason) => http.post(`/admin/rfqs/${ref(reference)}/decline`, { reason }, { silent: true }).then(data),

  // Catalog
  products: (params) => http.get('/admin/products', { params }).then(body),
  product: (id) => http.get(`/admin/products/${id}`).then(data),
  saveProduct: (id, formData) => {
    if (id) formData.append('_method', 'PATCH')
    return http.post(id ? `/admin/products/${id}` : '/admin/products', formData, { silent: true }).then(data)
  },
  deleteProduct: (id) => http.delete(`/admin/products/${id}`).then(body),
  restoreProduct: (id) => http.post(`/admin/products/${id}/restore`).then(data),
  syncTiers: (id, tiers) => http.put(`/admin/products/${id}/tiers`, { tiers }, { silent: true }).then(data),
  adjustStock: (id, payload) => http.post(`/admin/products/${id}/stock-adjustments`, payload, { silent: true }).then(body),
  categories: () => http.get('/admin/categories').then(data),
  saveCategory: (id, payload) => (id ? http.patch(`/admin/categories/${id}`, payload, { silent: true }) : http.post('/admin/categories', payload, { silent: true })).then(data),
  deleteCategory: (id) => http.delete(`/admin/categories/${id}`, { silent: true }).then(body),
  brands: () => http.get('/admin/brands').then(data),
  saveBrand: (id, payload) => (id ? http.patch(`/admin/brands/${id}`, payload, { silent: true }) : http.post('/admin/brands', payload, { silent: true })).then(data),
  deleteBrand: (id) => http.delete(`/admin/brands/${id}`).then(body),
  units: () => http.get('/admin/units').then(data),
  saveUnit: (id, payload) => (id ? http.patch(`/admin/units/${id}`, payload, { silent: true }) : http.post('/admin/units', payload, { silent: true })).then(data),
  customerGroups: () => http.get('/admin/customer-groups').then(data),
  saveCustomerGroup: (id, payload) => (id ? http.patch(`/admin/customer-groups/${id}`, payload, { silent: true }) : http.post('/admin/customer-groups', payload, { silent: true })).then(data),
  deleteCustomerGroup: (id) => http.delete(`/admin/customer-groups/${id}`, { silent: true }).then(body),
}
