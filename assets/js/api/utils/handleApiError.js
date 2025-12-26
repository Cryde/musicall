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
 * @property {Object.<string, {message: string, code: string|null}[]>} [violationsByField] - Violations grouped by field
 * @property {boolean} isValidationError - Whether this is a validation error
 * @property {Error} originalError - The original axios error
 */

/**
 * Normalizes API Platform errors into a consistent format
 * Handles constraint violations by aggregating messages and exposing field-level errors
 * @param {Error} error - The axios error object
 * @returns {never} - Always throws a normalized error
 */
export function handleApiError(error) {
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
