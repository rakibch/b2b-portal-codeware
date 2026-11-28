import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

vi.mock('@/api/auth', () => ({
  authApi: {
    login: vi.fn(async () => ({ token: 'tok-123', user: { id: 1, name: 'Tanvir', locale: 'bn', user_type: { value: 'buyer' }, roles: ['buyer_admin'], permissions: ['cart.manage'], can_see_prices: true, company: { kyc: { status: { value: 'approved' } } } } })),
    logout: vi.fn(async () => {}),
    me: vi.fn(),
    updateLocale: vi.fn(async () => {}),
  },
}))

const { useAuthStore } = await import('@/stores/auth')
const { useLocaleStore } = await import('@/stores/locale')
const { authApi } = await import('@/api/auth')

describe('auth store', () => {
  beforeEach(() => {
    localStorage.clear()
    sessionStorage.clear()
    setActivePinia(createPinia())
  })

  it('stores the token in sessionStorage unless "remember me"', async () => {
    const auth = useAuthStore()
    await auth.login({ email: 'a@b.c', password: 'x', remember: false })
    expect(sessionStorage.getItem('b2b.token')).toBe('tok-123')
    expect(localStorage.getItem('b2b.token')).toBeNull()
    expect(auth.isBuyerAdmin).toBe(true)
    expect(auth.canTransact).toBe(true)
    expect(auth.can('cart.manage')).toBe(true)
  })

  it('remembers across browser restarts when requested and clears on logout', async () => {
    const auth = useAuthStore()
    await auth.login({ email: 'a@b.c', password: 'x', remember: true })
    expect(localStorage.getItem('b2b.token')).toBe('tok-123')
    await auth.logout()
    expect(localStorage.getItem('b2b.token')).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
  })
})

describe('locale store', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('switches instantly, sets <html lang> and persists locally and remotely', async () => {
    const auth = useAuthStore()
    await auth.login({ email: 'a@b.c', password: 'x' })
    auth.user.locale = 'en'
    const store = useLocaleStore()

    store.setLocale('bn')

    expect(store.locale).toBe('bn')
    expect(document.documentElement.lang).toBe('bn')
    expect(localStorage.getItem('b2b.locale')).toBe('bn')
    expect(authApi.updateLocale).toHaveBeenCalledWith('bn')
  })

  it('ignores unsupported locales', () => {
    const store = useLocaleStore()
    store.setLocale('en')
    store.setLocale('fr')
    expect(store.locale).toBe('en')
  })
})
