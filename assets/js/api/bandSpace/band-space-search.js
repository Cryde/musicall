/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Searches every module of one band space at once, for the command palette and the chat attachment
   * picker.
   *
   * Below two characters the backend answers with the space's recent items instead of hits (#1046),
   * rather than an error. `type`, one of SEARCH_TYPES, narrows either to that kind; null means every
   * kind. `order`, one of RECENTS_ORDER, sorts those recents by the band's latest changes or by what
   * the caller opened last (#1047); a query ignores it.
   *
   * @param {string} bandSpaceId
   * @param {string} q
   * @param {?string} type
   * @param {?string} order
   */
  search(bandSpaceId, q, type = null, order = null) {
    return axios
      .get(Routing.generate('api_band_space_search_get_collection', { bandSpaceId }), {
        params: { q: q || undefined, type: type ?? undefined, order: order ?? undefined }
      })
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  /**
   * Adds an item to the caller's own « Ouverts par moi » history (#1047). Resolves either way: the
   * history is a convenience, and a failure must never get between a member and what they opened.
   *
   * @param {string} bandSpaceId
   * @param {string} type one of SEARCH_TYPES
   * @param {string} resourceId the record's own id, a palette result's `resource_id`
   */
  recordOpened(bandSpaceId, type, resourceId) {
    return axios
      .post(
        Routing.generate('api_band_space_opened_items_post', { bandSpaceId }),
        { type, resource_id: resourceId },
        { headers: { 'Content-Type': 'application/ld+json' } }
      )
      .catch((error) => {
        console.error('Failed to record the opened band space item:', error)
      })
  }
}
