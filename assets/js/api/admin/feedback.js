/** global: Routing */

import axios from 'axios'

export default {
  list({ page = 1, status = null, module = null, type = null } = {}) {
    const params = new URLSearchParams({ page: String(page) })
    if (status) params.append('status', status)
    if (module) params.append('module', module)
    if (type) params.append('type', type)

    return axios
      .get(`${Routing.generate('api_admin_feedbacks_list')}?${params.toString()}`)
      .then((resp) => resp.data)
  },

  updateStatus(id, status) {
    return axios.post(
      Routing.generate('api_admin_feedbacks_status_update', { id }),
      { status },
      { headers: { 'Content-Type': 'application/ld+json' } }
    )
  }
}
