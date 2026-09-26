/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Searches every module of one band space at once, for the command palette and the chat attachment
   * picker.
   *
   * Below two characters the backend answers with the space's recent items instead of hits, newest
   * first (#1046), rather than an error. `type`, one of SEARCH_TYPES, narrows either to that kind; null
   * means every kind.
   *
   * @param {string} bandSpaceId
   * @param {string} q
   * @param {?string} type
   */
  search(bandSpaceId, q, type = null) {
    return axios
      .get(Routing.generate('api_band_space_search_get_collection', { bandSpaceId }), {
        params: { q: q || undefined, type: type ?? undefined }
      })
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  }
}
