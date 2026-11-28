import { beforeEach, describe, expect, it, vi } from 'vitest'
import { configureHttp, http } from '@/api/http'
import { ApiError } from '@/api/ApiError'
import { i18n } from '@/i18n'

function adapterReturning(status, data) {
  return vi.fn(async (config) => {
    if (status >= 400) {
      const error = new Error('Request failed')
      error.config = config
      error.response = { status, data, headers: {}, config }
      error.isAxiosError = true
      throw error
    }
    return { status, data, headers: {}, config, statusText: 'OK' }
  })
}

describe('http client', () => {
  const hooks = { onUnauthenticated: vi.fn(), onForbidden: vi.fn(), onError: vi.fn() }

  beforeEach(() => {
    Object.values(hooks).forEach((h) => h.mockReset())
    configureHttp({ getToken: () => 'test-token', ...hooks })
    i18n.global.locale.value = 'bn'
  })

  it('sends bearer token and Accept-Language on every request', async () => {
    const adapter = adapterReturning(200, { ok: true })
    await http.get('/ping', { adapter })
    const config = adapter.mock.calls[0][0]
    expect(config.headers.Authorization).toBe('Bearer test-token')
    expect(config.headers['Accept-Language']).toBe('bn')
    expect(config.headers.Accept).toBe('application/json')
  })

  it('normalises validation errors without global toasts', async () => {
    const adapter = adapterReturning(422, { message: 'Invalid', errors: { email: ['Required'] } })
    const error = await http.post('/x', {}, { adapter }).catch((e) => e)
    expect(error).toBeInstanceOf(ApiError)
    expect(error.isValidation).toBe(true)
    expect(error.field('email')).toBe('Required')
    expect(hooks.onError).not.toHaveBeenCalled()
  })

  it('invokes the unauthenticated hook on 401', async () => {
    const adapter = adapterReturning(401, { message: 'Please sign in', code: 'unauthenticated' })
    await http.get('/me', { adapter }).catch(() => {})
    expect(hooks.onUnauthenticated).toHaveBeenCalledOnce()
  })

  it('surfaces business-rule codes and context', async () => {
    const adapter = adapterReturning(422, { message: 'Over limit', code: 'credit_limit_exceeded', context: { available_credit: '100.00' } })
    const error = await http.post('/orders', {}, { adapter }).catch((e) => e)
    expect(error.code).toBe('credit_limit_exceeded')
    expect(error.context.available_credit).toBe('100.00')
  })

  it('reports server errors through the error hook unless silent', async () => {
    await http.get('/boom', { adapter: adapterReturning(500, {}) }).catch(() => {})
    expect(hooks.onError).toHaveBeenCalledOnce()
    await http.get('/boom', { adapter: adapterReturning(500, {}), silent: true }).catch(() => {})
    expect(hooks.onError).toHaveBeenCalledOnce()
  })
})
