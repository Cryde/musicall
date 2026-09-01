/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * Every member's absences overlapping the period, not just the reader's: the drawer and the
   * calendar both narrow client side off this one response.
   */
  getAbsences(bandSpaceId, { from, to } = {}) {
    const params = {}
    if (from) params.from = from
    if (to) params.to = to
    return axios
      .get(Routing.generate('api_band_space_absences_get_collection', { bandSpaceId }), { params })
      .then((resp) => resp.data.member)
      .catch(handleApiError)
  },

  createAbsence(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_absences_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateAbsence(bandSpaceId, absenceId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_absences_patch', { bandSpaceId, id: absenceId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteAbsence(bandSpaceId, absenceId) {
    return axios
      .delete(Routing.generate('api_band_space_absences_delete', { bandSpaceId, id: absenceId }))
      .catch(handleApiError)
  }
}
