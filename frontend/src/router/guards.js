import { safeRedirect } from '@/utils/safeRedirect'

/** Landing route for a signed-in user: first registered route among the candidates. */
export function homeRouteFor(auth, hasRoute) {
  const candidates = auth.isStaff
    ? ['admin.dashboard', 'admin.orders', 'account']
    : auth.canTransact
      ? ['buyer.dashboard', 'buyer.catalog', 'account']
      : ['kyc.status', 'buyer.catalog', 'account']

  const name = candidates.find((n) => hasRoute(n))
  return name ? { name } : { path: '/account' }
}

/**
 * Pure navigation decision (unit-tested). Returns true to continue or a redirect location.
 *
 * Route meta:
 *  - guest: only for signed-out users (login, register)
 *  - public: reachable by anyone (accept invitation, reset password)
 *  - userType: 'buyer' | 'staff'
 *  - permissions: string[] — user needs at least one
 *  - approvedOnly: buyer company must be KYC-approved
 */
export function decideNavigation(to, auth, hasRoute) {
  const meta = to.meta ?? {}

  if (meta.public) return true

  if (meta.guest) {
    return auth.isAuthenticated ? homeRouteFor(auth, hasRoute) : true
  }

  if (!auth.isAuthenticated) {
    return { name: 'login', query: { redirect: safeRedirect(to.fullPath, '/') } }
  }

  if (meta.home) return homeRouteFor(auth, hasRoute)

  if (meta.userType === 'buyer' && !auth.isBuyer) return homeRouteFor(auth, hasRoute)
  if (meta.userType === 'staff' && !auth.isStaff) return homeRouteFor(auth, hasRoute)

  if (meta.permissions?.length && !meta.permissions.some((p) => auth.can(p))) {
    return { name: 'forbidden' }
  }

  if (meta.approvedOnly && !auth.canTransact) {
    return hasRoute('kyc.status') ? { name: 'kyc.status' } : { name: 'forbidden' }
  }

  return true
}
