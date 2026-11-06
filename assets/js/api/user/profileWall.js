/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

/** The profile wall (#1316). */
export default {
  getWall(username, before = null) {
    return axios
      .get(Routing.generate('api_profile_wall_get', { username, ...(before ? { before } : {}) }), {
        headers: { Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
  },

  /** A FormData when photos travel with it, a plain object otherwise. */
  createPost(payload) {
    const isMultipart = payload instanceof FormData
    return axios
      .post(Routing.generate('api_profile_posts_post'), payload, {
        headers: {
          Accept: 'application/ld+json',
          ...(isMultipart ? {} : { 'Content-Type': 'application/ld+json' })
        }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  updatePost(id, data) {
    return axios
      .patch(Routing.generate('api_profile_posts_patch', { id }), data, {
        headers: { 'Content-Type': 'application/merge-patch+json', Accept: 'application/ld+json' }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deletePost(id) {
    return axios.delete(Routing.generate('api_profile_posts_delete', { id })).catch(handleApiError)
  }
}
