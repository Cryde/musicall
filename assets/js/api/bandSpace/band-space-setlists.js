/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getSetlists(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_setlists_get_collection', { bandSpaceId }))
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  }
}
