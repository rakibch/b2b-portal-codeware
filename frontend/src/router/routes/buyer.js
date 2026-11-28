/**
 * Buyer portal routes. meta.permissions mirrors server permissions (UX only — the API enforces);
 * approvedOnly pages redirect pending/rejected companies to the KYC status page.
 */
const buyer = (meta = {}) => ({ userType: 'buyer', ...meta })

export const buyerRoutes = [
  { path: 'dashboard', name: 'buyer.dashboard', component: () => import('@/views/buyer/DashboardView.vue'), meta: buyer({ approvedOnly: true, permissions: ['buyer.dashboard.view'], titleKey: 'nav.dashboard' }) },
  { path: 'kyc', name: 'kyc.status', component: () => import('@/views/buyer/KycStatusView.vue'), meta: buyer({ titleKey: 'kyc.pending_title' }) },

  { path: 'catalog', name: 'buyer.catalog', component: () => import('@/views/buyer/CatalogView.vue'), meta: buyer({ permissions: ['catalog.view'], titleKey: 'nav.catalog' }) },
  { path: 'catalog/:slug', name: 'buyer.product', component: () => import('@/views/buyer/ProductDetailView.vue'), props: true, meta: buyer({ permissions: ['catalog.view'], titleKey: 'nav.catalog' }) },
  { path: 'quick-order', name: 'buyer.quick-order', component: () => import('@/views/buyer/QuickOrderView.vue'), meta: buyer({ approvedOnly: true, permissions: ['cart.manage'], titleKey: 'nav.quick_order' }) },
  { path: 'cart', name: 'buyer.cart', component: () => import('@/views/buyer/CartView.vue'), meta: buyer({ approvedOnly: true, permissions: ['cart.manage'], titleKey: 'nav.cart' }) },
  { path: 'checkout', name: 'buyer.checkout', component: () => import('@/views/buyer/CheckoutView.vue'), meta: buyer({ approvedOnly: true, permissions: ['orders.place'], titleKey: 'checkout.title' }) },

  { path: 'orders', name: 'buyer.orders', component: () => import('@/views/buyer/OrdersView.vue'), meta: buyer({ approvedOnly: true, permissions: ['orders.view_company'], titleKey: 'nav.orders' }) },
  { path: 'orders/:number', name: 'buyer.order', component: () => import('@/views/buyer/OrderDetailView.vue'), props: true, meta: buyer({ approvedOnly: true, permissions: ['orders.view_company'], titleKey: 'orders.order' }) },
  // Payment gateway return URL (FRONTEND_URL/orders/{order}/payment-result)
  { path: 'orders/:number/payment-result', name: 'buyer.payment-result', redirect: (to) => ({ name: 'buyer.order', params: { number: to.params.number }, query: { payment: to.query.status } }) },

  { path: 'rfqs', name: 'buyer.rfqs', component: () => import('@/views/buyer/RfqsView.vue'), meta: buyer({ approvedOnly: true, permissions: ['rfqs.create'], titleKey: 'nav.rfqs' }) },
  { path: 'rfqs/new', name: 'buyer.rfq-new', component: () => import('@/views/buyer/RfqCreateView.vue'), meta: buyer({ approvedOnly: true, permissions: ['rfqs.create'], titleKey: 'rfq.new' }) },
  { path: 'rfqs/:reference', name: 'buyer.rfq', component: () => import('@/views/buyer/RfqDetailView.vue'), props: true, meta: buyer({ approvedOnly: true, permissions: ['rfqs.create'], titleKey: 'nav.rfqs' }) },

  { path: 'invoices', name: 'buyer.invoices', component: () => import('@/views/buyer/InvoicesView.vue'), meta: buyer({ approvedOnly: true, permissions: ['invoices.view_company'], titleKey: 'nav.invoices' }) },
  { path: 'payments', name: 'buyer.payments', component: () => import('@/views/buyer/PaymentsView.vue'), meta: buyer({ approvedOnly: true, permissions: ['payments.submit'], titleKey: 'nav.payments' }) },
  { path: 'ledger', name: 'buyer.ledger', component: () => import('@/views/buyer/LedgerView.vue'), meta: buyer({ approvedOnly: true, permissions: ['ledger.view_company'], titleKey: 'nav.ledger' }) },

  { path: 'company', name: 'buyer.company', component: () => import('@/views/buyer/CompanyView.vue'), meta: buyer({ titleKey: 'nav.company' }) },
  { path: 'addresses', name: 'buyer.addresses', component: () => import('@/views/buyer/AddressesView.vue'), meta: buyer({ titleKey: 'nav.addresses' }) },
  { path: 'team', name: 'buyer.team', component: () => import('@/views/buyer/TeamView.vue'), meta: buyer({ approvedOnly: true, permissions: ['company.manage_users'], titleKey: 'nav.team' }) },
]
