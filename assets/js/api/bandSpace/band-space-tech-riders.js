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
  },

  createItem(bandSpaceId, riderId, data) {
    return axios
      .post(
        Routing.generate('api_band_space_tech_rider_items_post', { bandSpaceId, riderId }),
        data,
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * Send only the keys being changed: the API tells an absent field from an explicit null, so
   * a title-only save must not carry `content` or it would overwrite what is there.
   */
  updateItem(bandSpaceId, riderId, itemId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_tech_rider_items_patch', {
          bandSpaceId,
          riderId,
          id: itemId
        }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteItem(bandSpaceId, riderId, itemId) {
    return axios
      .delete(
        Routing.generate('api_band_space_tech_rider_items_delete', {
          bandSpaceId,
          riderId,
          id: itemId
        })
      )
      .catch(handleApiError)
  },

  /**
   * PUT, because the whole grid is replaced. Positions are not sent: array order is the order
   * the server stores, so the payload cannot disagree with the list the user is looking at.
   *
   * Returns the updated item, which carries the saved rows with their new ids.
   */
  savePatchList(bandSpaceId, riderId, itemId, { inputs, outputs }) {
    return axios
      .put(
        Routing.generate('api_band_space_tech_rider_patch_list_put', {
          bandSpaceId,
          riderId,
          itemId
        }),
        { inputs, outputs },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  reorderItems(bandSpaceId, riderId, positions) {
    return axios
      .post(
        Routing.generate('api_band_space_tech_rider_items_reorder', { bandSpaceId, riderId }),
        { positions },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .catch(handleApiError)
  }
}
