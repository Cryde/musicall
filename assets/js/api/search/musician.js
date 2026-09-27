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
    radius = null,
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
      // Only the guided search bounds the distance (#1084); the filters only sort by it.
      if (radius) params.radius = radius
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
  /** Which wider search would find something (#1084): yes or no only, never how many. */
  getWidening({
    type = null,
    instrument = null,
    styles = [],
    latitude = null,
    longitude = null,
    radius = null
  }) {
    const params = {}
    if (type !== null) params.type = type
    if (instrument !== null) params.instrument = instrument
    if (styles.length > 0) params.styles = styles
    if (latitude !== null && longitude !== null) {
      params.latitude = latitude
      params.longitude = longitude
      if (radius) params.radius = radius
    }
    return axios
      .get(Routing.generate('api_musician_search_widen', params))
      .then((resp) => resp.data)
  },
  getSearchAnnouncesFilters({ search }) {
    return axios
      .get(Routing.generate('api_musician_announces_filters', { search }))
      .then((resp) => resp.data)
  }
}
