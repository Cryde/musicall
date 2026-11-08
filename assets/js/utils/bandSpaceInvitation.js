// The path of the invitation page (route app_band_invitation), the one place a token rides in a return_url.
const INVITATION_PAGE = /^\/band\/invitation\/([^/?#]+)(?:[?#]|$)/

/**
 * The invitation token carried by a `return_url` that points back to the invitation page, or null.
 *
 * @param {unknown} returnUrl
 * @returns {string|null}
 */
export function invitationTokenInReturnUrl(returnUrl) {
  if (typeof returnUrl !== 'string') {
    return null
  }

  return returnUrl.match(INVITATION_PAGE)?.[1] ?? null
}

/**
 * The sign-up form reached from the login form. Register only reads `invitation_token` to show the band
 * and prefill the email, so the token is lifted out of the way back to the invitation page (#1359).
 *
 * @param {string|null} returnUrl the login form's own `return_url`
 * @returns {{name: string, query: {return_url?: string, invitation_token?: string}}}
 */
export function registerRouteFromLogin(returnUrl) {
  const query = {}
  if (returnUrl) {
    query.return_url = returnUrl
  }

  const invitationToken = invitationTokenInReturnUrl(returnUrl)
  if (invitationToken) {
    query.invitation_token = invitationToken
  }

  return { name: 'app_register', query }
}

export const INVITATION_REFUSAL = Object.freeze({
  ALREADY_MEMBER: 'already_member',
  NO_LONGER_VALID: 'no_longer_valid',
  OTHER_RECIPIENT: 'other_recipient'
})

/**
 * Why the invitation page could not be opened, from the status of the info call, or null when unknown.
 * A 409 there only ever means the viewer is already a member of the band, whatever the invitation's status.
 *
 * @param {number|undefined} status
 * @returns {string|null}
 */
export function invitationRefusalOnOpen(status) {
  if (status === 404) {
    return INVITATION_REFUSAL.NO_LONGER_VALID
  }
  if (status === 409) {
    return INVITATION_REFUSAL.ALREADY_MEMBER
  }

  return null
}

/**
 * Why accepting or declining was refused, or null when unknown. A 409 is left out on purpose: here it
 * is also a space pending deletion, whose own message already says what happened.
 *
 * @param {number|undefined} status
 * @returns {string|null}
 */
export function invitationRefusalOnAnswer(status) {
  if (status === 404) {
    return INVITATION_REFUSAL.NO_LONGER_VALID
  }
  if (status === 403) {
    return INVITATION_REFUSAL.OTHER_RECIPIENT
  }

  return null
}
