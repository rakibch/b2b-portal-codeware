import { describe, expect, it } from 'vitest'
import { decideNavigation, homeRouteFor } from '@/router/guards'
import { visibleNavigation, buyerNavigation } from '@/router/navigation'

const registered = new Set(['login', 'forbidden', 'account', 'buyer.dashboard', 'buyer.catalog', 'buyer.cart', 'kyc.status', 'admin.dashboard'])
const hasRoute = (name) => registered.has(name)

function authState(overrides = {}) {
  const permissions = new Set(overrides.permissions ?? [])
  return {
    isAuthenticated: true,
    isStaff: false,
    isBuyer: true,
    canTransact: true,
    can: (p) => permissions.has(p),
    ...overrides,
  }
}

describe('decideNavigation', () => {
  it('sends guests to login with a safe redirect', () => {
    const result = decideNavigation({ meta: {}, fullPath: '/orders' }, authState({ isAuthenticated: false }), hasRoute)
    expect(result).toEqual({ name: 'login', query: { redirect: '/orders' } })
  })

  it('keeps signed-in users away from guest pages', () => {
    expect(decideNavigation({ meta: { guest: true } }, authState(), hasRoute)).toEqual({ name: 'buyer.dashboard' })
  })

  it('separates buyer and staff portals', () => {
    const staff = authState({ isStaff: true, isBuyer: false })
    expect(decideNavigation({ meta: { userType: 'buyer' } }, staff, hasRoute)).toEqual({ name: 'admin.dashboard' })
  })

  it('enforces permissions', () => {
    const to = { meta: { permissions: ['kyc.review'] } }
    expect(decideNavigation(to, authState(), hasRoute)).toEqual({ name: 'forbidden' })
    expect(decideNavigation(to, authState({ permissions: ['kyc.review'] }), hasRoute)).toBe(true)
  })

  it('redirects unapproved buyers to KYC status for transactional pages', () => {
    const pending = authState({ canTransact: false })
    expect(decideNavigation({ meta: { approvedOnly: true } }, pending, hasRoute)).toEqual({ name: 'kyc.status' })
  })

  it('lets public routes through regardless of auth', () => {
    expect(decideNavigation({ meta: { public: true } }, authState({ isAuthenticated: false }), hasRoute)).toBe(true)
  })

  it('home resolves pending buyers to KYC status', () => {
    expect(homeRouteFor(authState({ canTransact: false }), hasRoute)).toEqual({ name: 'kyc.status' })
  })
})

describe('visibleNavigation', () => {
  it('shows the dashboard (not verification status) once approved', () => {
    const sections = visibleNavigation(buyerNavigation, { hasRoute, can: () => true, canTransact: true })
    const names = sections.flatMap((s) => s.items.map((i) => i.name))
    expect(names).toContain('buyer.dashboard')
    expect(names).not.toContain('kyc.status')
  })

  it('hides unregistered, unauthorised and KYC-locked items', () => {
    const sections = visibleNavigation(buyerNavigation, {
      hasRoute,
      can: (p) => ['catalog.view', 'cart.manage'].includes(p),
      canTransact: false,
    })
    const names = sections.flatMap((s) => s.items.map((i) => i.name))
    expect(names).toEqual(['kyc.status', 'buyer.catalog'])
  })
})
