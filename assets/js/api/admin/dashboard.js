/** global: Routing */

import axios from 'axios'

export default {
  getGeneralMetrics() {
    return axios
      .get(Routing.generate('_api_/admin/dashboard/general_get'))
      .then((resp) => resp.data)
  },

  getUserMetrics(from, to) {
    return axios
      .get(Routing.generate('_api_/admin/dashboard/users_get', { from, to }))
      .then((resp) => resp.data)
  },

  getTimeSeries(metric, from, to) {
    return axios
      .get(Routing.generate('_api_/admin/dashboard/time-series_get', { metric, from, to }))
      .then((resp) => resp.data)
  },

  getContentOverview(from, to) {
    return axios
      .get(Routing.generate('_api_/admin/dashboard/content-overview_get', { from, to }))
      .then((resp) => resp.data)
  }
}
