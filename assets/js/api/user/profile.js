/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  getPublicProfile(username) {
    return axios
      .get(Routing.generate('api_public_profile_get', { username }))
      .then((resp) => resp.data)
  },

  getMyProfile() {
    return axios
      .get(Routing.generate('api_user_profile_get'))
      .then((resp) => resp.data)
  },

  updateMyProfile(data) {
    return axios
      .patch(
        Routing.generate('api_user_profile_edit'),
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

  getMySocialLinks() {
    return axios
      .get(Routing.generate('api_user_social_links_get_collection'))
      .then((resp) => resp.data.member)
  },

  addSocialLink(data) {
    return axios
      .post(
        Routing.generate('api_user_social_links_post'),
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

  deleteSocialLink(id) {
    return axios
      .delete(Routing.generate('api_user_social_links_delete', { id }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  uploadCoverPicture(file) {
    const formData = new FormData()
    formData.append('imageFile', file, 'cover.jpg')

    return axios
      .post(Routing.generate('api_user_profile_cover_picture_post'), formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  deleteCoverPicture() {
    return axios
      .delete(Routing.generate('api_user_profile_cover_picture_delete'))
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
