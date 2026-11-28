import { describe, expect, it } from 'vitest'
import { createMemoryHistory, createRouter } from 'vue-router'
import { routes } from '@/router'
import { buyerRoutes } from '@/router/routes/buyer'

describe('route table', () => {
  const router = createRouter({ history: createMemoryHistory(), routes })
  buyerRoutes.forEach((r) => router.addRoute('portal', r))

  it('resolves "/" to the portal home (not the auth layout)', () => {
    expect(router.resolve('/').name).toBe('home')
  })

  it('resolves auth and buyer pages', () => {
    expect(router.resolve('/login').name).toBe('login')
    expect(router.resolve('/register').name).toBe('register')
    expect(router.resolve('/orders/ORD-2026-000001').name).toBe('buyer.order')
    expect(router.resolve('/catalog/fortified-soybean-oil').params.slug).toBe('fortified-soybean-oil')
    expect(router.resolve('/no/such/page').name).toBe('not-found')
  })

  it('maps the payment-gateway return URL onto the order page', () => {
    const resolved = router.resolve('/orders/ORD-1/payment-result?status=verified')
    expect(resolved.matched.at(-1).redirect).toBeTypeOf('function')
  })

  it('marks transactional buyer pages as KYC-gated', () => {
    const gated = buyerRoutes.filter((r) => r.meta?.approvedOnly).map((r) => r.name)
    expect(gated).toEqual(expect.arrayContaining(['buyer.cart', 'buyer.checkout', 'buyer.orders', 'buyer.ledger']))
    expect(gated).not.toContain('buyer.catalog')
    expect(gated).not.toContain('kyc.status')
  })
})
