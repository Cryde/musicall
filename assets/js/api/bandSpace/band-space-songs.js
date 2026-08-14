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
  },

  getAttachedFiles(bandSpaceId, songId) {
    return axios
      .get(Routing.generate('api_band_space_song_files_get_collection', { bandSpaceId, songId }))
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  uploadFile(bandSpaceId, songId, file, onProgress) {
    const formData = new FormData()
    formData.append('uploadedFile', file)
    return axios
      .post(
        Routing.generate('api_band_space_song_files_attach', { bandSpaceId, songId }),
        formData,
        {
          headers: { 'Content-Type': 'multipart/form-data' },
          onUploadProgress: (progressEvent) => {
            if (onProgress && progressEvent.total) {
              const percent = Math.round((progressEvent.loaded * 100) / progressEvent.total)
              onProgress(percent)
            }
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  detachFile(bandSpaceId, songId, fileId, { archive = false } = {}) {
    const baseUrl = Routing.generate('api_band_space_song_files_detach', {
      bandSpaceId,
      songId,
      id: fileId
    })
    const url = archive ? `${baseUrl}?archive=true` : baseUrl
    return axios.delete(url).catch(handleApiError)
  }
}
