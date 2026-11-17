/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

const JSON_LD = { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }

/** The sound engineer kind of service (#1410): its page, its search and its owner's form. */
export default {
  getPage(username) {
    return axios
      .get(Routing.generate('api_sound_engineer_page_get', { username }))
      .then((resp) => resp.data)
  },

  /** The search, its params from soundEngineerSearchParams(). */
  search(params) {
    return axios
      .get(Routing.generate('api_services_sound_engineering_search', params))
      .then((resp) => resp.data)
  },

  /**
   * One sound of « Avant, après », as a blob: no byte ranges, so Safari only plays it from memory. Asked by its
   * `audio_id`, so a replaced sound is never played from the browser's cache.
   */
  fetchAudio(serviceId, side, audioId) {
    return axios
      .get(Routing.generate('api_service_audio_get', { id: serviceId, side, v: audioId }), {
        responseType: 'blob'
      })
      .then((resp) => resp.data)
  },

  getForm(serviceId) {
    return axios
      .get(Routing.generate('api_user_services_sound_engineering_get', { id: serviceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** A merge patch: what is left out keeps its stored value. */
  saveForm(serviceId, data) {
    return axios
      .patch(
        Routing.generate('api_user_services_sound_engineering_patch', { id: serviceId }),
        data,
        {
          headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  uploadAudio(serviceId, side, { file, title, credit }) {
    const form = new FormData()
    form.append('file', file)
    if (title) form.append('title', title)
    if (credit) form.append('credit', credit)
    return axios
      .post(Routing.generate('api_user_services_audio_upload', { id: serviceId, side }), form, {
        headers: { Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteAudio(serviceId, side) {
    return axios
      .delete(Routing.generate('api_user_services_audio_delete', { id: serviceId, side }))
      .catch(handleApiError)
  },

  addMedia(serviceId, data) {
    return axios
      .post(Routing.generate('api_user_services_media_create', { id: serviceId }), data, {
        headers: JSON_LD
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteMedia(serviceId, mediaId) {
    return axios
      .delete(Routing.generate('api_user_services_media_delete', { id: serviceId, mediaId }))
      .catch(handleApiError)
  }
}
