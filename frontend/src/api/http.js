import axios from 'axios'
import { i18n } from '@/i18n'
import { ApiError } from './ApiError'

/**
 * Single Axios instance for the Laravel API.
 * - Authorization: Bearer <Sanctum token> (token provider injected by the auth store)
 * - Accept-Language: en|bn on every request (drives localised API messages)
 * - Errors normalised to ApiError; 401/403/429/5xx handled globally via registered handlers
 */
export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL || '/api/v1',
  timeout: 30000,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  withCredentials: false,
})

const hooks = {
  getToken: () => null,
  onUnauthenticated: () => {},
  onForbidden: () => {},
  onError: () => {},
}

/** Wire auth/UI behaviour without circular imports (called once from main.js). */
export function configureHttp(options) {
  Object.assign(hooks, options)
}

http.interceptors.request.use((config) => {
  const token = hooks.getToken()
  if (token) config.headers.Authorization = `Bearer ${token}`
  config.headers['Accept-Language'] = i18n.global.locale.value
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    const t = i18n.global.t
    const config = error.config ?? {}

    if (axios.isCancel(error)) return Promise.reject(error)

    if (!error.response) {
      const apiError = new ApiError({
        status: 0,
        code: error.code === 'ECONNABORTED' ? 'timeout' : 'network',
        message: t(error.code === 'ECONNABORTED' ? 'errors.timeout' : 'errors.network'),
      })
      if (!config.silent) hooks.onError(apiError)
      return Promise.reject(apiError)
    }

    const { status, data } = error.response
    const apiError = new ApiError({
      status,
      code: data?.code ?? null,
      message: data?.message || fallbackMessage(status, t),
      errors: data?.errors ?? {},
      context: data?.context ?? null,
    })

    if (status === 401 && !config.skipAuthRedirect) {
      hooks.onUnauthenticated(apiError)
    } else if (status === 403 && !config.silent) {
      hooks.onForbidden(apiError)
    } else if (!config.silent && status !== 422 && status !== 404) {
      hooks.onError(apiError)
    }

    return Promise.reject(apiError)
  },
)

function fallbackMessage(status, t) {
  if (status === 403) return t('errors.forbidden')
  if (status === 404) return t('errors.not_found')
  if (status === 422) return t('errors.validation')
  if (status === 429) return t('errors.too_many_requests')
  if (status >= 500) return t('errors.server')
  return t('errors.generic')
}

/** Saves a binary response (PDF/XLSX) with the server-provided filename. */
export async function download(url, params = {}, fallbackName = 'download') {
  const response = await http.get(url, { params, responseType: 'blob' })
  const disposition = response.headers['content-disposition'] ?? ''
  const match = /filename="?([^";]+)"?/i.exec(disposition)
  const name = match ? match[1] : fallbackName
  const href = URL.createObjectURL(response.data)
  const link = document.createElement('a')
  link.href = href
  link.download = name
  link.rel = 'noopener'
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(href), 1000)
}
