/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getSetlists(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_setlists_get_collection', { bandSpaceId }))
      .then((resp) => resp.data.member ?? [])
      .catch(handleApiError)
  },

  getSetlist(bandSpaceId, setlistId) {
    return axios
      .get(Routing.generate('api_band_space_setlists_get_item', { bandSpaceId, id: setlistId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  createSetlist(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_setlists_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateSetlist(bandSpaceId, setlistId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_setlists_patch', { bandSpaceId, id: setlistId }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteSetlist(bandSpaceId, setlistId) {
    return axios
      .delete(Routing.generate('api_band_space_setlists_delete', { bandSpaceId, id: setlistId }))
      .catch(handleApiError)
  },

  duplicateSetlist(bandSpaceId, setlistId) {
    return axios
      .post(
        Routing.generate('api_band_space_setlists_duplicate', { bandSpaceId, id: setlistId }),
        {},
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  reorderItems(bandSpaceId, setlistId, positions) {
    return axios
      .post(
        Routing.generate('api_band_space_setlists_reorder', { bandSpaceId, id: setlistId }),
        { positions },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .catch(handleApiError)
  },

  addItem(bandSpaceId, setlistId, data) {
    return axios
      .post(
        Routing.generate('api_band_space_setlist_items_post', { bandSpaceId, setlistId }),
        data,
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updateItem(bandSpaceId, setlistId, itemId, data) {
    return axios
      .patch(
        Routing.generate('api_band_space_setlist_items_patch', {
          bandSpaceId,
          setlistId,
          id: itemId
        }),
        data,
        { headers: { 'Content-Type': 'application/merge-patch+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  removeItem(bandSpaceId, setlistId, itemId) {
    return axios
      .delete(
        Routing.generate('api_band_space_setlist_items_delete', {
          bandSpaceId,
          setlistId,
          id: itemId
        })
      )
      .catch(handleApiError)
  },

  uploadFile(bandSpaceId, setlistId, file, onProgress) {
    const formData = new FormData()
    formData.append('uploadedFile', file)
    return axios
      .post(
        Routing.generate('api_band_space_setlist_files_attach', { bandSpaceId, setlistId }),
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

  detachFile(bandSpaceId, setlistId, fileId, { archive = false } = {}) {
    const baseUrl = Routing.generate('api_band_space_setlist_files_detach', {
      bandSpaceId,
      setlistId,
      id: fileId
    })
    const url = archive ? `${baseUrl}?archive=true` : baseUrl
    return axios.delete(url).catch(handleApiError)
  },

  buildPdfUrl(bandSpaceId, setlistId, options = {}) {
    const baseUrl = Routing.generate('api_band_space_setlists_pdf_export', {
      bandSpaceId,
      id: setlistId
    })
    const params = new URLSearchParams()
    if (options.layout) params.set('layout', options.layout)
    if (options.showTempo !== undefined) params.set('showTempo', options.showTempo ? '1' : '0')
    if (options.showKey !== undefined) params.set('showKey', options.showKey ? '1' : '0')
    if (options.showDurations !== undefined)
      params.set('showDurations', options.showDurations ? '1' : '0')
    if (options.showNotes !== undefined) params.set('showNotes', options.showNotes ? '1' : '0')
    if (options.showTransitions !== undefined)
      params.set('showTransitions', options.showTransitions ? '1' : '0')
    const qs = params.toString()
    return qs ? `${baseUrl}?${qs}` : baseUrl
  }
}
