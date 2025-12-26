/** global: Routing */

import axios from 'axios'

/**
 * Normalizes API Platform errors into a consistent format
 * @param {Error} error - The axios error object
 * @returns {never} - Always throws a normalized error
 */
function handleApiError(error) {
  const data = error.response?.data
  const violations = data?.violations || []
  const isValidationError = violations.length > 0

  let message

  if (isValidationError) {
    message = violations.map((v) => v.message).join('. ')
  } else {
    message =
      data?.['hydra:description'] || data?.detail || error.message || 'Une erreur est survenue'
  }

  const violationsByField = violations.reduce((acc, violation) => {
    const field = violation.propertyPath || '_global'
    if (!acc[field]) {
      acc[field] = []
    }
    acc[field].push({
      message: violation.message,
      code: violation.code || null
    })
    return acc
  }, {})

  const normalizedError = new Error(message)
  normalizedError.status = error.response?.status
  normalizedError.violations = violations
  normalizedError.violationsByField = violationsByField
  normalizedError.isValidationError = isValidationError
  normalizedError.originalError = error

  throw normalizedError
}

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
  }
}
