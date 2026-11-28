/**
 * Defensive Web Storage access: private mode / blocked storage must never crash the app.
 */
function safe(storage) {
  return {
    get(key, fallback = null) {
      try {
        const value = storage().getItem(key)
        return value === null ? fallback : value
      } catch {
        return fallback
      }
    },
    set(key, value) {
      try {
        storage().setItem(key, value)
      } catch {
        /* storage unavailable — ignore */
      }
    },
    remove(key) {
      try {
        storage().removeItem(key)
      } catch {
        /* storage unavailable — ignore */
      }
    },
  }
}

export const local = safe(() => window.localStorage)
export const session = safe(() => window.sessionStorage)
