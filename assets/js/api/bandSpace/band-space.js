/** global: Routing */

import axios from 'axios'

export default {
  getMyBandSpace() {
    return axios
      .get(Routing.generate('api_band_spaces_get_collection'))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
  },

  create(name) {
    return axios
      .post(
        Routing.generate('api_band_spaces_post_collection'),
        { name },
        {
          headers: {
            'Content-Type': 'application/ld+json',
            'Accept': 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
  }
}
