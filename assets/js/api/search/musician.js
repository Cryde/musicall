/** global: Routing */

import axios from 'axios'

export default {
  searchAnnounces({ instrument, styles, type }) {
    return axios
      .get(
        Routing.generate('api_musician_announces_search_collection', {
          instrument,
          styles,
          type
        })
      )
      .then((resp) => resp.data)
  },
    getSearchAnnouncesFilters({search}) {
        return axios
        .get(Routing.generate('api_musician_announces_filters', {search}))
        .then((resp) => resp.data)
    }
}
