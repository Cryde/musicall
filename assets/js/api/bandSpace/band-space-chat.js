/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * The whole Hydra body, not just `member`: the store needs `totalItems` to know whether there is
   * older history left to load.
   */
  getMessages(bandSpaceId, { page } = {}) {
    return axios
      .get(Routing.generate('api_band_space_chat_messages_get_collection', { bandSpaceId }), {
        params: { page }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  markAsRead(bandSpaceId) {
    return axios
      .post(Routing.generate('api_band_space_chat_read', { bandSpaceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  postMessage(bandSpaceId, content) {
    return axios
      .post(
        Routing.generate('api_band_space_chat_messages_post', { bandSpaceId }),
        { content },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
