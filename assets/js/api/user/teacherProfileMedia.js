/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getMedia() {
    return axios
      .get(Routing.generate('api_teacher_profile_media_get_collection'))
      .then((resp) => resp.data)
  },

  addMedia(data) {
    return axios
      .post(
        Routing.generate('api_teacher_profile_media_create'),
        data,
        {
          headers: {
            'Content-Type': 'application/ld+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteMedia(id) {
    return axios
      .delete(Routing.generate('api_teacher_profile_media_delete', { id }))
      .catch(handleApiError)
  }
}
