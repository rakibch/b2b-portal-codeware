/** Admin portal routes (staff only). Permissions mirror the API; the API remains the authority. */
const staff = (permissions, titleKey) => ({ userType: 'staff', permissions, titleKey })

export const adminRoutes = [
  { path: 'admin', name: 'admin.dashboard', component: () => import('@/views/admin/AdminDashboardView.vue'), meta: staff(['admin.dashboard.view'], 'nav.dashboard') },

  { path: 'admin/kyc', name: 'admin.kyc', component: () => import('@/views/admin/KycQueueView.vue'), meta: staff(['kyc.review'], 'nav.kyc_queue') },
  { path: 'admin/kyc/:id', name: 'admin.kyc-review', component: () => import('@/views/admin/KycReviewView.vue'), props: true, meta: staff(['kyc.review'], 'nav.kyc_queue') },
  { path: 'admin/companies', name: 'admin.companies', component: () => import('@/views/admin/CompaniesView.vue'), meta: staff(['companies.view'], 'nav.companies') },
  { path: 'admin/companies/:id', name: 'admin.company', component: () => import('@/views/admin/CompanyDetailView.vue'), props: true, meta: staff(['companies.view'], 'nav.companies') },
  { path: 'admin/companies/:id/new-order', name: 'admin.order-new', component: () => import('@/views/admin/StaffOrderCreateView.vue'), props: true, meta: staff(['orders.create_on_behalf'], 'orders.on_behalf') },

  { path: 'admin/orders', name: 'admin.orders', component: () => import('@/views/admin/AdminOrdersView.vue'), meta: staff(['orders.view'], 'nav.orders') },
  { path: 'admin/orders/:number', name: 'admin.order', component: () => import('@/views/admin/AdminOrderDetailView.vue'), props: true, meta: staff(['orders.view'], 'orders.order') },
  { path: 'admin/fulfilment', name: 'admin.fulfilment', component: () => import('@/views/admin/FulfilmentView.vue'), meta: staff(['orders.fulfil'], 'nav.fulfilment') },

  { path: 'admin/payments', name: 'admin.payments', component: () => import('@/views/admin/AdminPaymentsView.vue'), meta: staff(['payments.view', 'payments.verify'], 'nav.payments') },
  { path: 'admin/receivables', name: 'admin.receivables', component: () => import('@/views/admin/ReceivablesView.vue'), meta: staff(['invoices.view'], 'nav.receivables') },

  { path: 'admin/rfqs', name: 'admin.rfqs', component: () => import('@/views/admin/AdminRfqsView.vue'), meta: staff(['rfqs.view'], 'nav.rfqs') },
  { path: 'admin/rfqs/:reference', name: 'admin.rfq', component: () => import('@/views/admin/AdminRfqDetailView.vue'), props: true, meta: staff(['rfqs.view'], 'nav.rfqs') },

  { path: 'admin/products', name: 'admin.products', component: () => import('@/views/admin/ProductsView.vue'), meta: staff(['catalog.view'], 'nav.products') },
  { path: 'admin/products/new', name: 'admin.product-new', component: () => import('@/views/admin/ProductEditorView.vue'), meta: staff(['catalog.manage'], 'admin.new_product') },
  { path: 'admin/products/:id(\\d+)', name: 'admin.product-edit', component: () => import('@/views/admin/ProductEditorView.vue'), props: true, meta: staff(['catalog.view'], 'admin.edit_product') },
  { path: 'admin/categories', name: 'admin.categories', component: () => import('@/views/admin/CategoriesView.vue'), meta: staff(['catalog.manage'], 'nav.categories') },
  { path: 'admin/pricing', name: 'admin.pricing', component: () => import('@/views/admin/PricingView.vue'), meta: staff(['pricing.manage'], 'nav.pricing') },
]
