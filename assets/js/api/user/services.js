/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

/** The member's own services, for « Mes services » (#1406). */
export default {
  getMyServices() {
    return axios.get(Routing.generate('api_user_services_list')).then((resp) => resp.data.member)
  },

  addService(kind) {
    return axios
      .post(
        Routing.generate('api_user_services_create'),
        { kind },
        { headers: { 'Content-Type': 'application/ld+json', Accept: 'application/ld+json' } }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteService(id) {
    return axios.delete(Routing.generate('api_user_services_delete', { id })).catch(handleApiError)
  },

  publishService(id) {
    return axios
      .post(Routing.generate('api_user_services_publish', { id }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  unpublishService(id) {
    return axios
      .post(Routing.generate('api_user_services_unpublish', { id }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
