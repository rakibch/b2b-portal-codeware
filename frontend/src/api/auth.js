import { http } from './http'

export const authApi = {
  login: (payload) => http.post('/auth/login', payload, { skipAuthRedirect: true, silent: true }).then((r) => r.data),
  register: (formData) => http.post('/auth/register', formData, { silent: true }).then((r) => r.data),
  me: () => http.get('/auth/me', { skipAuthRedirect: true, silent: true }).then((r) => r.data.data),
  logout: () => http.post('/auth/logout', null, { skipAuthRedirect: true, silent: true }),
  logoutAll: () => http.post('/auth/logout-all'),
  updateLocale: (locale) => http.patch('/auth/me/locale', { locale }, { silent: true }),
  changePassword: (payload) => http.put('/auth/me/password', payload).then((r) => r.data),
  forgotPassword: (email) => http.post('/auth/forgot-password', { email }).then((r) => r.data),
  resetPassword: (payload) => http.post('/auth/reset-password', payload).then((r) => r.data),
  invitation: (token) => http.get('/auth/invitations', { params: { token }, silent: true }).then((r) => r.data),
  acceptInvitation: (payload) => http.post('/auth/invitations/accept', payload, { silent: true }).then((r) => r.data),
}
