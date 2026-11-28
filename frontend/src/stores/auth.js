import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { authApi } from '@/api/auth'
import { local, session } from '@/utils/storage'

const TOKEN_KEY = 'b2b.token'

/**
 * Token storage: "keep me signed in" → localStorage, otherwise sessionStorage (cleared when the
 * browser closes). Tokens expire server-side (Sanctum expiration) and are revoked on logout.
 */
function readToken() {
  return session.get(TOKEN_KEY) || local.get(TOKEN_KEY) || null
}

function writeToken(token, remember) {
  local.remove(TOKEN_KEY)
  session.remove(TOKEN_KEY)
  if (token) (remember ? local : session).set(TOKEN_KEY, token)
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref(readToken())
  const user = ref(null)
  const initialised = ref(false)
  let initPromise = null

  const isAuthenticated = computed(() => Boolean(token.value && user.value))
  const permissions = computed(() => new Set(user.value?.permissions ?? []))
  const roles = computed(() => user.value?.roles ?? [])
  const isStaff = computed(() => user.value?.user_type?.value === 'staff')
  const isBuyer = computed(() => user.value?.user_type?.value === 'buyer')
  const isBuyerAdmin = computed(() => roles.value.includes('buyer_admin'))
  const isSuperAdmin = computed(() => roles.value.includes('super_admin'))
  const company = computed(() => user.value?.company ?? null)
  const kycStatus = computed(() => company.value?.kyc?.status?.value ?? null)
  /** Approved buyer (or staff) — wholesale prices, cart and ordering are unlocked. */
  const canTransact = computed(() => Boolean(user.value?.can_see_prices) && (isStaff.value || kycStatus.value === 'approved'))

  function can(permission) {
    return permissions.value.has(permission)
  }

  function canAny(list = []) {
    return list.some((p) => permissions.value.has(p))
  }

  function hasRole(role) {
    return roles.value.includes(role)
  }

  function setSession(payload, remember) {
    token.value = payload.token
    user.value = payload.user
    writeToken(payload.token, remember)
  }

  async function login({ email, password, remember = false }) {
    const payload = await authApi.login({ email, password, device_name: navigator.userAgent.slice(0, 100) })
    setSession(payload, remember)
    return payload.user
  }

  async function fetchMe() {
    user.value = await authApi.me()
    return user.value
  }

  /** Restores the session on app start (idempotent; concurrent callers share one request). */
  function init() {
    if (initialised.value) return Promise.resolve(user.value)
    if (!initPromise) {
      initPromise = (async () => {
        if (token.value) {
          try {
            await fetchMe()
          } catch {
            clear()
          }
        }
        initialised.value = true
        return user.value
      })()
    }
    return initPromise
  }

  function clear() {
    token.value = null
    user.value = null
    writeToken(null)
  }

  async function logout() {
    try {
      if (token.value) await authApi.logout()
    } finally {
      clear()
    }
  }

  async function logoutEverywhere() {
    try {
      await authApi.logoutAll()
    } finally {
      clear()
    }
  }

  return {
    token,
    user,
    initialised,
    isAuthenticated,
    permissions,
    roles,
    isStaff,
    isBuyer,
    isBuyerAdmin,
    isSuperAdmin,
    company,
    kycStatus,
    canTransact,
    can,
    canAny,
    hasRole,
    setSession,
    login,
    fetchMe,
    init,
    clear,
    logout,
    logoutEverywhere,
  }
})
