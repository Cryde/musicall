/** global: Routing */

import axios from 'axios'

export default {
  getGeneralMetrics() {
    return axios
      .get(Routing.generate('_api_/admin/dashboard/general_get'))
      .then((resp) => resp.data)
  },

  getUserMetrics() {
    return axios
      .get(Routing.generate('_api_/admin/dashboard/users_get'))
      .then((resp) => resp.data)
  }
}
