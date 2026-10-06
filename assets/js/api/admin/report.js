/** global: Routing */

import axios from 'axios'

export default {
  list({ page = 1, status = null } = {}) {
    const params = new URLSearchParams({ page: String(page) })
    if (status) params.append('status', status)

    return axios
      .get(`${Routing.generate('api_admin_reports_list')}?${params.toString()}`)
      .then((resp) => resp.data)
  },

  get(id) {
    return axios.get(Routing.generate('api_admin_reports_get', { id })).then((resp) => resp.data)
  },

  dismiss(id) {
    return axios.post(Routing.generate('api_admin_reports_dismiss', { id }))
  },

  suspendAuthor(id, reason) {
    return axios.post(
      Routing.generate('api_admin_reports_suspend_author', { id }),
      { reason },
      { headers: { 'Content-Type': 'application/ld+json' } }
    )
  }
}
