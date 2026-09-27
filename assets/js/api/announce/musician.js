/** global: Routing */

import axios from 'axios'

export default {
  /** `type` narrows to the announces bands (1) or musicians (2) posted; none is all of them. */
  getLastAnnounces(type = null) {
    return axios
      .get(Routing.generate('api_musician_announces_get_last_collection', type ? { type } : {}))
      .then((resp) => resp.data)
  },

  /** « Annonces pour vous » (#1082): the announces answering the member's latest ones, best first. */
  getMatches() {
    return axios
      .get(Routing.generate('api_user_musician_announce_matches'))
      .then((resp) => resp.data.matches)
  },

  getByCurrentUser() {
    return axios
      .get(Routing.generate('api_musician_announces_get_self_collection'))
      .then((resp) => resp.data)
  },

  create({ type, note, styles, instrument, locationName, longitude, latitude }) {
    return axios
      .post(
        Routing.generate('api_musician_announces_post'),
        {
          type,
          note,
          styles,
          instrument,
          locationName,
          longitude,
          latitude
        },
        {
          headers: {
            'Content-Type': 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
  },

  delete(id) {
    return axios
      .delete(Routing.generate('api_musician_announces_delete', { id }))
      .then((resp) => resp.data)
  }
}
