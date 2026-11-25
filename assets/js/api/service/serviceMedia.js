/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

const JSON_LD = { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' }

/** The media of a member's own service, whatever its kind (#1410): each one added and removed at once. */
export default {
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
