/** Only allow same-app relative redirects (prevents open-redirect via ?redirect=). */
export function safeRedirect(target, fallback = '/') {
  if (typeof target !== 'string') return fallback
  if (!target.startsWith('/') || target.startsWith('//') || target.startsWith('/\\')) return fallback
  return target
}
