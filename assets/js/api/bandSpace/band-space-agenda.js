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
  },

  createEntry(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_agenda_entries_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateEntry(bandSpaceId, entryId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_agenda_entries_patch', { bandSpaceId, id: entryId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteEntry(bandSpaceId, entryId) {
    return axios
      .delete(
        Routing.generate('api_band_space_agenda_entries_delete', { bandSpaceId, id: entryId })
      )
      .catch(handleApiError)
  }
}
