import { download, http } from './http'

const data = (r) => r.data.data
const body = (r) => r.data

export const buyerApi = {
  // Dashboard & checkout
  dashboard: () => http.get('/buyer/dashboard').then(data),
  checkoutPreview: () => http.get('/buyer/checkout/preview').then(data),

  // Company / KYC
  company: () => http.get('/buyer/company').then(data),
  updateCompany: (payload) => http.patch('/buyer/company', payload, { silent: true }).then(data),
  submitDocuments: (formData) => http.post('/buyer/company/documents', formData, { silent: true }).then(body),
  downloadDocument: (id, name) => download(`/documents/${id}/download`, {}, name),

  // Addresses
  addresses: (type) => http.get('/buyer/addresses', { params: type ? { type } : {} }).then(data),
  createAddress: (payload) => http.post('/buyer/addresses', payload, { silent: true }).then(data),
  updateAddress: (id, payload) => http.put(`/buyer/addresses/${id}`, payload, { silent: true }).then(data),
  deleteAddress: (id) => http.delete(`/buyer/addresses/${id}`).then(body),

  // Team
  members: () => http.get('/buyer/team/members').then(data),
  updateMember: (id, payload) => http.patch(`/buyer/team/members/${id}`, payload, { silent: true }).then(data),
  invitations: () => http.get('/buyer/team/invitations').then(data),
  invite: (payload) => http.post('/buyer/team/invitations', payload, { silent: true }).then(body),
  revokeInvitation: (id) => http.delete(`/buyer/team/invitations/${id}`).then(body),

  // Quick order
  validateRows: (rows, signal) => http.post('/buyer/quick-order/validate', { rows }, { signal, silent: true }).then(data),
  addRowsToCart: (rows) => http.post('/buyer/quick-order/add-to-cart', { rows }, { silent: true }).then(data),
  importFile: (file) => {
    const form = new FormData()
    form.append('file', file)
    return http.post('/buyer/quick-order/import', form, { silent: true }).then(data)
  },
  downloadTemplate: () => download('/buyer/quick-order/template', {}, 'quick-order-template.csv'),

  // RFQs
  rfqs: (params) => http.get('/buyer/rfqs', { params }).then(body),
  rfq: (reference) => http.get(`/buyer/rfqs/${encodeURIComponent(reference)}`).then(data),
  createRfq: (payload) => http.post('/buyer/rfqs', payload, { silent: true }).then(body),
  acceptRfq: (reference) => http.post(`/buyer/rfqs/${encodeURIComponent(reference)}/accept`).then(data),
  rejectRfq: (reference) => http.post(`/buyer/rfqs/${encodeURIComponent(reference)}/reject`).then(data),
  cancelRfq: (reference) => http.post(`/buyer/rfqs/${encodeURIComponent(reference)}/cancel`).then(data),
  orderFromRfq: (reference, payload) => http.post(`/buyer/rfqs/${encodeURIComponent(reference)}/order`, payload, { silent: true }).then(body),

  // Orders
  orders: (params) => http.get('/buyer/orders', { params }).then(body),
  order: (number) => http.get(`/buyer/orders/${encodeURIComponent(number)}`).then(data),
  checkout: (payload) => http.post('/buyer/orders', payload, { silent: true }).then(body),
  cancelOrder: (number, reason) => http.post(`/buyer/orders/${encodeURIComponent(number)}/cancel`, { reason }, { silent: true }).then(body),
  approveOrder: (number) => http.post(`/buyer/orders/${encodeURIComponent(number)}/approve`).then(body),
  rejectOrder: (number, reason) => http.post(`/buyer/orders/${encodeURIComponent(number)}/reject`, { reason }, { silent: true }).then(body),
  reorder: (number) => http.post(`/buyer/orders/${encodeURIComponent(number)}/reorder`).then(body),
  payOnline: (number) => http.post(`/buyer/orders/${encodeURIComponent(number)}/pay-online`).then(body),

  // Finance
  invoices: (params) => http.get('/buyer/invoices', { params }).then(body),
  invoicePdf: (number, lang) => download(`/invoices/${encodeURIComponent(number)}/pdf`, { lang }, `${number}-${lang}.pdf`),
  challanPdf: (challan, lang) => download(`/shipments/${encodeURIComponent(challan)}/challan`, { lang }, `${challan}-${lang}.pdf`),
  payments: (params) => http.get('/buyer/payments', { params }).then(body),
  submitPayment: (formData) => http.post('/buyer/payments', formData, { silent: true }).then(body),
  paymentSlip: (reference) => download(`/payments/${encodeURIComponent(reference)}/slip`, {}, `${reference}-slip`),
  ledger: (params) => http.get('/buyer/ledger', { params }).then(body),
  exportLedger: (params) => download('/buyer/ledger/export', params, 'statement.xlsx'),
}
