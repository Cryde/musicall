/** global: Routing */

import axios from 'axios'

/** The musician searches, for the admin (#1075). */
export default {
  getOverview(from, to) {
    return axios
      .get(Routing.generate('api_admin_searches_overview', { from, to }))
      .then((resp) => resp.data)
  },

  listAiSearches(page = 1) {
    return axios
      .get(Routing.generate('api_admin_searches_ai_list', { page }))
      .then((resp) => resp.data)
  }
}
