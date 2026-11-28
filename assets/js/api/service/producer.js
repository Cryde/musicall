/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

/** The producer kind of service (#1413): its page and its owner's form; its search, media and works are shared. */
export default {
  getPage(username) {
    return axios
      .get(Routing.generate('api_producer_page_get', { username }))
      .then((resp) => resp.data)
  },

  getForm(serviceId) {
    return axios
      .get(Routing.generate('api_user_services_production_get', { id: serviceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** A merge patch: what is left out keeps its stored value. */
  saveForm(serviceId, data) {
    return axios
      .patch(Routing.generate('api_user_services_production_patch', { id: serviceId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
