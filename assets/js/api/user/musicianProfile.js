/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getPublicMusicianProfile(username) {
    return axios
      .get(Routing.generate('api_musician_profile_get', { username }))
      .then((resp) => resp.data)
  },

  getMyMusicianProfile() {
    return axios
      .get(Routing.generate('api_musician_profile_edit_get'))
      .then((resp) => resp.data)
  },

  createMusicianProfile(data) {
    return axios
      .post(
        Routing.generate('api_musician_profile_create'),
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

  updateMusicianProfile(data) {
    return axios
      .patch(
        Routing.generate('api_musician_profile_edit'),
        data,
        {
          headers: {
            'Content-Type': 'application/merge-patch+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteMusicianProfile() {
    return axios
      .delete(Routing.generate('api_musician_profile_delete'))
      .catch(handleApiError)
  }
}
