/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getSongs(bandSpaceId, { includeArchived = false } = {}) {
    const params = {}
    if (includeArchived) params.includeArchived = 1
    return axios
      .get(Routing.generate('api_band_space_songs_get_collection', { bandSpaceId }), { params })
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

  deleteSong(bandSpaceId, songId) {
    return axios
      .delete(Routing.generate('api_band_space_songs_delete', { bandSpaceId, id: songId }))
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
