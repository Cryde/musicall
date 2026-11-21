/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  /**
   * @param {boolean} archived true lists the trash instead of the live repertoire
   */
  getSongs(bandSpaceId, { archived = false } = {}) {
    const url = Routing.generate('api_band_space_songs_get_collection', { bandSpaceId })
    return axios
      .get(archived ? `${url}?archived=true` : url)
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  getSong(bandSpaceId, songId) {
    return axios
      .get(Routing.generate('api_band_space_songs_get_item', { bandSpaceId, id: songId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  createSong(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_songs_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateSong(bandSpaceId, songId, data) {
    return axios
      .patch(Routing.generate('api_band_space_songs_patch', { bandSpaceId, id: songId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** Several titles to the trash at once (#1063). */
  archiveSongs(bandSpaceId, songIds) {
    return axios
      .post(
        Routing.generate('api_band_space_songs_archive_bulk', { bandSpaceId }),
        { song_ids: songIds },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  /** Soft delete: the title moves to the trash, it is not destroyed. */
  deleteSong(bandSpaceId, songId) {
    return axios
      .delete(Routing.generate('api_band_space_songs_delete', { bandSpaceId, id: songId }))
      .catch(handleApiError)
  },

  restoreSong(bandSpaceId, songId) {
    return axios
      .post(
        Routing.generate('api_band_space_songs_restore', { bandSpaceId, id: songId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
