/** global: Routing */

import axios from 'axios'

export default {
  list() {
    return axios
      .get(Routing.generate('api_user_blocks_get_collection'))
      .then((resp) => resp.data.member)
  },

  /** Always a 201, a repeat included. The blocked user is never told. */
  block(userId) {
    return axios
      .post(
        Routing.generate('api_user_blocks_post'),
        { user_id: userId },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .then((resp) => resp.data)
  },

  unblock(userId) {
    return axios.delete(Routing.generate('api_user_blocks_delete', { userId }))
  }
}
