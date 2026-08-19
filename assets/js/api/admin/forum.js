/** global: Routing */

import axios from 'axios'

export default {
  getPendingReports() {
    return axios
      .get(Routing.generate('api_admin_forum_reports_list'))
      .then((resp) => resp.data.member)
  },

  resolveReport(id) {
    return axios.post(
      Routing.generate('api_admin_forum_reports_resolve', { id }),
      {},
      {
        headers: { 'Content-Type': 'application/ld+json' }
      }
    )
  }
}
