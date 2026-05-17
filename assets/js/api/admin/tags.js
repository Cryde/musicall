/** global: Routing */

import axios from 'axios'

export default {
  list() {
    return axios.get(Routing.generate('api_admin_tags_list')).then((resp) => resp.data.member)
  },

  create(label) {
    return axios
      .post(
        Routing.generate('api_admin_tags_create'),
        { label },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  },

  remove(id) {
    return axios.delete(Routing.generate('api_admin_tags_delete', { id }))
  }
}
