/** global: Routing */

import axios from 'axios'

export default {
  getFeed(page = 1) {
    return axios
      .get(Routing.generate('api_user_notifications_get_collection', { page }))
      .then((resp) => resp.data)
      .then((data) => ({ items: data.member, total: data.totalItems }))
  },

  getCount() {
    return axios
      .get(Routing.generate('api_user_notifications_count'))
      .then((resp) => resp.data.unread)
  },

  markRead(id) {
    return axios.post(
      Routing.generate('api_user_notifications_read', { id }),
      {},
      { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
    )
  },

  markAllRead() {
    return axios.post(
      Routing.generate('api_user_notifications_mark_all_read'),
      {},
      { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
    )
  }
}
