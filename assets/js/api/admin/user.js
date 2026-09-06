/** global: Routing */

import axios from 'axios'

export default {
  search({ search, page = 1 }) {
    const params = new URLSearchParams({ search, page: String(page) })

    return axios
      .get(`${Routing.generate('api_admin_users_search')}?${params.toString()}`)
      .then((resp) => resp.data)
  },

  get(id) {
    return axios.get(Routing.generate('api_admin_users_get', { id })).then((resp) => resp.data)
  },

  updateRoles(id, roles) {
    return axios.post(
      Routing.generate('api_admin_users_roles_update', { id }),
      { roles },
      { headers: { 'Content-Type': 'application/ld+json' } }
    )
  }
}
