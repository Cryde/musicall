/** The problem type the API gives a profile its member made private, still a 404 (#1308). */
export const PRIVATE_PROFILE_ERROR_TYPE = '/errors/profile-private'

/** Whether a failed profile request means « private » rather than « not found ». */
export function isPrivateProfileError(error) {
  return error?.response?.status === 404 && error.response.data?.type === PRIVATE_PROFILE_ERROR_TYPE
}

/** Usernames are matched without case by the API, so the page compares them the same way. */
export function isSameUsername(a, b) {
  return typeof a === 'string' && typeof b === 'string' && a.toLowerCase() === b.toLowerCase()
}
