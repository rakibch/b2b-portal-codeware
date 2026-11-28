/**
 * Sidebar navigation. Items are shown only when their route is registered, the user holds one of
 * the listed permissions, and (for transactional pages) the buyer's company is KYC-approved.
 */
export const buyerNavigation = [
  {
    titleKey: null,
    items: [{ name: 'buyer.dashboard', labelKey: 'nav.dashboard', icon: 'LayoutDashboard' }],
  },
  {
    titleKey: 'nav.section_ordering',
    items: [
      { name: 'buyer.catalog', labelKey: 'nav.catalog', icon: 'Store', permissions: ['catalog.view'] },
      { name: 'buyer.quick-order', labelKey: 'nav.quick_order', icon: 'Zap', permissions: ['cart.manage'], approvedOnly: true },
      { name: 'buyer.cart', labelKey: 'nav.cart', icon: 'ShoppingCart', permissions: ['cart.manage'], approvedOnly: true, badge: 'cart' },
      { name: 'buyer.orders', labelKey: 'nav.orders', icon: 'Package', permissions: ['orders.view_company'], approvedOnly: true },
      { name: 'buyer.rfqs', labelKey: 'nav.rfqs', icon: 'MessageSquareQuote', permissions: ['rfqs.create'], approvedOnly: true },
    ],
  },
  {
    titleKey: 'nav.section_finance',
    items: [
      { name: 'buyer.invoices', labelKey: 'nav.invoices', icon: 'FileText', permissions: ['invoices.view_company'], approvedOnly: true },
      { name: 'buyer.payments', labelKey: 'nav.payments', icon: 'Wallet', permissions: ['payments.submit'], approvedOnly: true },
      { name: 'buyer.ledger', labelKey: 'nav.ledger', icon: 'BookOpen', permissions: ['ledger.view_company'], approvedOnly: true },
    ],
  },
  {
    titleKey: 'nav.section_company',
    items: [
      { name: 'buyer.company', labelKey: 'nav.company', icon: 'Building2' },
      { name: 'buyer.addresses', labelKey: 'nav.addresses', icon: 'MapPin' },
      { name: 'buyer.team', labelKey: 'nav.team', icon: 'Users', permissions: ['company.manage_users'], approvedOnly: true },
    ],
  },
]

export const adminNavigation = [
  {
    titleKey: null,
    items: [{ name: 'admin.dashboard', labelKey: 'nav.dashboard', icon: 'LayoutDashboard', permissions: ['admin.dashboard.view'] }],
  },
  {
    titleKey: 'nav.section_customers',
    items: [
      { name: 'admin.kyc', labelKey: 'nav.kyc_queue', icon: 'ShieldCheck', permissions: ['kyc.review'], badge: 'kyc' },
      { name: 'admin.companies', labelKey: 'nav.companies', icon: 'Building2', permissions: ['companies.view'] },
      { name: 'admin.rfqs', labelKey: 'nav.rfqs', icon: 'MessageSquareQuote', permissions: ['rfqs.view'] },
    ],
  },
  {
    titleKey: 'nav.section_operations',
    items: [
      { name: 'admin.orders', labelKey: 'nav.orders', icon: 'Package', permissions: ['orders.view'] },
      { name: 'admin.fulfilment', labelKey: 'nav.fulfilment', icon: 'Truck', permissions: ['orders.fulfil'] },
    ],
  },
  {
    titleKey: 'nav.section_finance',
    items: [
      { name: 'admin.payments', labelKey: 'nav.payments', icon: 'Wallet', permissions: ['payments.view', 'payments.verify'] },
      { name: 'admin.receivables', labelKey: 'nav.receivables', icon: 'Landmark', permissions: ['invoices.view'] },
    ],
  },
  {
    titleKey: 'nav.section_catalog',
    items: [
      { name: 'admin.products', labelKey: 'nav.products', icon: 'Boxes', permissions: ['catalog.view'] },
      { name: 'admin.categories', labelKey: 'nav.categories', icon: 'FolderTree', permissions: ['catalog.manage'] },
      { name: 'admin.pricing', labelKey: 'nav.pricing', icon: 'BadgePercent', permissions: ['pricing.manage'] },
    ],
  },
]

/** @returns {Array} sections with only the items this user can open */
export function visibleNavigation(sections, { hasRoute, can, canTransact }) {
  return sections
    .map((section) => ({
      ...section,
      items: section.items.filter(
        (item) =>
          hasRoute(item.name) &&
          (!item.permissions || item.permissions.some((p) => can(p))) &&
          (!item.approvedOnly || canTransact),
      ),
    }))
    .filter((section) => section.items.length > 0)
}
