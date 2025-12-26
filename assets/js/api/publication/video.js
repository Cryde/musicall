/** global: Routing */

import axios from 'axios'

/**
 * @typedef {Object} Violation
 * @property {string} propertyPath - The field that has the violation
 * @property {string} message - The error message
 * @property {string} [code] - Optional error code
 */

/**
 * @typedef {Object} ApiError
 * @property {string} message - Aggregated error message
 * @property {number} [status] - HTTP status code
 * @property {Violation[]} [violations] - Array of field-level violations
 * @property {Object.<string, string[]>} [violationsByField] - Violations grouped by field
 * @property {boolean} isValidationError - Whether this is a validation error
 * @property {Error} originalError - The original axios error
 */

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
    acc[field].push(violation.message)
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
  /**
   * Get a preview of a YouTube video
   * @param {string} url - The YouTube video URL
   * @returns {Promise<{url: string, title: string, description: string, imageUrl: string}>}
   */
  getPreview(url) {
    return axios
      .get(Routing.generate('api_publication_video_preview', { url }))
      .then((resp) => resp.data)
      .catch(handleApiError)
  },

  /**
   * Add a new video discovery
   * @param {Object} params
   * @param {string} params.url - The YouTube video URL
   * @param {string} params.title - The video title
   * @param {string} params.description - The video description
   * @param {string|null} [params.categoryId] - Optional category ID
   * @returns {Promise<Object>}
   */
  addVideo({ url, title, description, categoryId = null }) {
    const payload = {
      url,
      title,
      description
    }

    if (categoryId) {
      payload.category = `/api/publication_sub_categories/${categoryId}`
    }

    return axios
      .post(Routing.generate('api_publication_video_add'), payload, {
        headers: {
          'Content-Type': 'application/ld+json',
          Accept: 'application/ld+json'
        }
      })
      .then((resp) => resp.data)
      .catch(handleApiError)
  }
}
