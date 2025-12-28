/** global: Routing */

import axios from 'axios'

export default {
  searchAnnounces({ instrument, styles, type, latitude = null, longitude = null }) {
    const params = { instrument, styles, type }
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
