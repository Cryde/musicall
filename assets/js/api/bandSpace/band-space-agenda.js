/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getAgenda(bandSpaceId, { from, to } = {}) {
    const params = {}
    if (from) params.from = from
    if (to) params.to = to
    return axios
      .get(Routing.generate('api_band_space_agenda_get_collection', { bandSpaceId }), { params })
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  }
}
