/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getNotes(bandSpaceId) {
    return axios
      .get(Routing.generate('api_band_space_notes_get_collection', { bandSpaceId }))
      .then((resp) => resp.data)
      .then((resp) => resp.member)
      .catch(handleApiError)
  },

  getNote(bandSpaceId, noteId) {
    return axios
      .get(Routing.generate('api_band_space_notes_get_item', { bandSpaceId, id: noteId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  create(bandSpaceId, data) {
    return axios
      .post(Routing.generate('api_band_space_notes_post', { bandSpaceId }), data, {
        headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  update(bandSpaceId, noteId, data) {
    return axios
      .patch(Routing.generate('api_band_space_notes_patch', { bandSpaceId, id: noteId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteNote(bandSpaceId, noteId) {
    return axios
      .delete(Routing.generate('api_band_space_notes_delete', { bandSpaceId, id: noteId }))
      .catch(handleApiError)
  }
}
