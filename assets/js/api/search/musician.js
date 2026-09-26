/** global: Routing */

import axios from 'axios'

export default {
  searchAnnounces({
    instrument = null,
    styles = null,
    type = null,
    latitude = null,
    longitude = null,
    location = null,
    landing = false,
    page = 1
  }) {
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
      // Only kept for the frequent searches (#1075); the search goes by the coordinates.
      if (location) params.location = location
    }
    // A landing page's list on arrival, which the server does not record as a search (#1075).
    if (landing) params.landing = '1'
    return axios
      .get(Routing.generate('api_musician_announces_search_collection', params))
      .then((resp) => resp.data)
  },
  /** « Recherches fréquentes » (#1075): what enough different people searched for lately. */
  getFrequentSearches() {
    return axios
      .get(Routing.generate('api_musician_search_frequent'))
      .then((resp) => resp.data.searches)
  },
  getSearchAnnouncesFilters({ search }) {
    return axios
      .get(Routing.generate('api_musician_announces_filters', { search }))
      .then((resp) => resp.data)
  }
}
