/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * The caller's own feed for this band, never anyone else's, and never the URL: only the token's
   * hash is stored, so the address exists once, in the response to generate().
   */
  getFeed(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_agenda_feed_get', { bandSpaceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Generates the feed, or rotates it: either way the previous URL stops working. */
  generateFeed(bandSpaceId) {
    return axios
      .post(
        Routing.generate('api_band_space_agenda_feed_post', { bandSpaceId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  revokeFeed(bandSpaceId) {
    return axios
      .delete(Routing.generate('api_band_space_agenda_feed_delete', { bandSpaceId }))
      .catch(handleApiError)
  }
}
