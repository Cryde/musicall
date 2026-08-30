/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Searches every module of one band space at once, for the command palette.
   *
   * The backend answers with an empty collection below two characters rather than an error, so the
   * caller never has to special case a short query.
   *
   * @param {string} bandSpaceId
   * @param {string} q
   */
  search(bandSpaceId, q) {
    return axios
      .get(Routing.generate('api_band_space_search_get_collection', { bandSpaceId }), {
        params: { q }
      })
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  }
}
