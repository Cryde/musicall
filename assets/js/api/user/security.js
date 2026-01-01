/** global: Routing */

import axios from 'axios'
import { handleApiError } from '../utils/handleApiError.js'

export default {
  login(username, password) {
    return axios
      .post(Routing.generate('api_login_check'), { username, password })
      .then((resp) => resp.data)
  },
  refreshToken() {
    return axios.get(Routing.generate('api_refresh_token')).then((resp) => resp.data)
  },
  logout() {
    return axios.post(Routing.generate('api_token_invalidate')).then((resp) => resp.data)
  },
  register({ username, email, password }) {
    return axios
      .post(
        Routing.generate('api_users_register'),
        { username, email, password },
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
  getSelf() {
    return axios.get(Routing.generate('api_users_get_self')).then((resp) => resp.data)
  },
  changePassword({ oldPassword, newPassword }) {
    return axios
      .post(
        Routing.generate('api_users_change_password_post'),
        { oldPassword, newPassword },
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
  changeProfilePicture(formData) {
    return axios
      .post(Routing.generate('api_user_profile_picture_post'), formData)
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  requestResetPassword(login) {
    return axios
      .post(
        Routing.generate('api_users_request_reset_password'),
        { login },
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

  resetPassword(token, password) {
    return axios
      .post(
        Routing.generate('api_users_reset_password', { token }),
        { password },
        {
          headers: {
            'Content-Type': 'application/ld+json',
            Accept: 'application/ld+json'
          }
        }
      )
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
