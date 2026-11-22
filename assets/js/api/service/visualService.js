/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

/** Each kind's routes, by its ServiceKind value. */
const ROUTES = {
  photography: {
    page: 'api_photographer_page_get',
    form: 'api_user_services_photography_get',
    save: 'api_user_services_photography_patch'
  },
  video: {
    page: 'api_videographer_page_get',
    form: 'api_user_services_video_get',
    save: 'api_user_services_video_patch'
  }
}

/**
 * The photographer and videographer kinds of service (#1411): a page and its owner's form each, the same shape for
 * both. Their search, media and works are shared with the other kinds.
 */
export default {
  getPage(kind, username) {
    return axios.get(Routing.generate(ROUTES[kind].page, { username })).then((resp) => resp.data)
  },

  getForm(kind, serviceId) {
    return axios
      .get(Routing.generate(ROUTES[kind].form, { id: serviceId }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /** A merge patch: what is left out keeps its stored value. */
  saveForm(kind, serviceId, data) {
    return axios
      .patch(Routing.generate(ROUTES[kind].save, { id: serviceId }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
