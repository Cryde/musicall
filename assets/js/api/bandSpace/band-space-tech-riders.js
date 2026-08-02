/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * @param {boolean} archived true lists the archive instead of the live riders
   */
  getTechRiders(bandSpaceId, { archived = false } = {}) {
    const url = Routing.generate('api_band_space_tech_riders_get_collection', { bandSpaceId })
    return axios
      .get(archived ? `${url}?archived=true` : url)
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  getTechRider(bandSpaceId, riderId) {
    return axios
      .get(Routing.generate('api_band_space_tech_riders_get_item', { bandSpaceId, id: riderId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  createTechRider(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_tech_riders_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateTechRider(bandSpaceId, riderId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_tech_riders_patch', { bandSpaceId, id: riderId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Soft delete: the rider moves to the archive, it is not destroyed. */
  archiveTechRider(bandSpaceId, riderId) {
    return axios
      .delete(Routing.generate('api_band_space_tech_riders_delete', { bandSpaceId, id: riderId }))
      .catch(handleApiError)
  },

  unarchiveTechRider(bandSpaceId, riderId) {
    return axios
      .post(
        Routing.generate('api_band_space_tech_riders_unarchive', { bandSpaceId, id: riderId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
