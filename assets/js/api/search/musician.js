/** global: Routing */

import axios from 'axios'

export default {
  searchAnnounces({ instrument = null, styles = null, type = null, latitude = null, longitude = null, page = 1 }) {
    const params = { page }
    if (type !== null) {
      params.type = type
    }
    if (instrument !== null) {
      params.instrument = instrument
    }
    if (styles !== null && styles.length > 0) {
      params.styles = styles
    }
    if (latitude !== null && longitude !== null) {
      params.latitude = latitude
      params.longitude = longitude
    }
    return axios
      .get(Routing.generate('api_musician_announces_search_collection', params))
      .then((resp) => resp.data)
  },
  getSearchAnnouncesFilters({ search }) {
    return axios
      .get(Routing.generate('api_musician_announces_filters', { search }))
      .then((resp) => resp.data)
  }
}
