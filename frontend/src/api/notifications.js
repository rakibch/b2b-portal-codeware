import { http } from './http'

export const notificationsApi = {
  list: (params = {}) => http.get('/notifications', { params, silent: true }).then((r) => r.data),
  unreadCount: () => http.get('/notifications/unread-count', { silent: true }).then((r) => r.data.unread_count),
  markRead: (id) => http.post(`/notifications/${id}/read`, null, { silent: true }).then((r) => r.data.unread_count),
  markAllRead: () => http.post('/notifications/read-all', null, { silent: true }).then((r) => r.data.unread_count),
  announcements: () => http.get('/announcements', { silent: true }).then((r) => r.data.data),
}
