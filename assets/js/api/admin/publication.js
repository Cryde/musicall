/** global: Routing */

import axios from 'axios'

export default {
  getPendingPublications() {
    return axios.get(Routing.generate('api_admin_publications_pending_list')).then((resp) => resp.data.member)
  },

  approvePublication(id) {
    return axios.post(Routing.generate('api_admin_publications_approve', { id }), {}, {
      headers: { 'Content-Type': 'application/ld+json' }
    })
  },

  rejectPublication(id) {
    return axios.post(Routing.generate('api_admin_publications_reject', { id }), {}, {
      headers: { 'Content-Type': 'application/ld+json' }
    })
  }
}
