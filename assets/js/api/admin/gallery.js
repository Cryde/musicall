/** global: Routing */

import axios from 'axios'

export default {
  getPendingGalleries() {
    return axios
      .get(Routing.generate('api_admin_galleries_pending_list'))
      .then((resp) => resp.data.member)
  },

  approveGallery(id) {
    return axios.post(
      Routing.generate('api_admin_galleries_approve', { id }),
      {},
      {
        headers: { 'Content-Type': 'application/ld+json' }
      }
    )
  },

  rejectGallery(id) {
    return axios.post(
      Routing.generate('api_admin_galleries_reject', { id }),
      {},
      {
        headers: { 'Content-Type': 'application/ld+json' }
      }
    )
  }
}
