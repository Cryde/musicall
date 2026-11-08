import { isSafeReturnUrl } from './returnUrl.js'

/**
 * The steps of signing in. A link from one of them forwards the return it was handed rather than its
 * own address: nobody signed in wants to land back on the login form.
 */
const SIGN_IN_ROUTES = new Set([
  'app_login',
  'app_register',
  'app_verify_email',
  'app_forgot_password',
  'app_reset_password'
])

/**
 * Where to send a visitor who is not signed in, for a route that requires it.
 *
 * A route may name its own landing place through `meta.unauthenticatedRedirect`, so a shared link to
 * a gated module can explain itself instead of presenting a bare password box. Anything without one
 * goes to the login form. Either way the destination is told where the visitor was going, so the
 * Band Space presentation can still bring them back to the deep link they opened (#1302).
 *
 * Extracted from the guard in App.vue because this branch carries two deliberate carve-outs now, the
 * marketing landing page and this one, and it is the kind of conditional a third change gets wrong.
 *
 * @param {{meta?: {unauthenticatedRedirect?: string}, fullPath?: string}} to a resolved route
 * @returns {{name: string, query?: {return_url: string}}}
 */
export function resolveUnauthenticatedRedirect(to) {
  return routeReturningTo(to?.meta?.unauthenticatedRedirect ?? 'app_login', to?.fullPath)
}

/**
 * Where a member who is already signed in goes instead of a sign-in step: the return the step was
 * handed when it is safe, the home page otherwise.
 *
 * @param {{query?: Record<string, unknown>}} to a resolved route
 * @returns {string|{name: string}}
 */
export function resolveSignedInRedirect(to) {
  const returnUrl = to?.query?.return_url

  return isSafeReturnUrl(returnUrl) ? returnUrl : { name: 'app_home' }
}

/**
 * The login form, told where to go once signed in.
 *
 * @param {string|undefined} returnPath the router `fullPath` to come back to
 * @returns {{name: string, query?: {return_url: string}}}
 */
export function loginRouteReturningTo(returnPath) {
  return routeReturningTo('app_login', returnPath)
}

/**
 * A route told where to come back to once signed in, under `return_url`, the key every sign-in step
 * reads. An unsafe return is dropped at each hop, so a crafted one goes no further than the page it
 * came in on.
 *
 * @param {string} name
 * @param {unknown} returnPath
 * @param {Record<string, string>} [query] what the route needs besides the return
 * @returns {{name: string, query?: Record<string, string>}}
 */
export function routeReturningTo(name, returnPath, query = {}) {
  const withReturn = isSafeReturnUrl(returnPath) ? { ...query, return_url: returnPath } : query

  return Object.keys(withReturn).length > 0 ? { name, query: withReturn } : { name }
}

/**
 * Where a visitor on `route` comes back to after signing in: the return the page was handed, or else
 * the page itself, unless it is a sign-in step.
 *
 * @param {{name?: string, fullPath?: string, query?: Record<string, unknown>}} route
 * @returns {string|null}
 */
export function returnPathOf(route) {
  const handed = route?.query?.return_url
  if (isSafeReturnUrl(handed)) {
    return handed
  }
  if (SIGN_IN_ROUTES.has(route?.name)) {
    return null
  }

  return isSafeReturnUrl(route?.fullPath) ? route.fullPath : null
}

/**
 * Where creating a Band Space from its presentation brings a visitor back after signing up: the deep
 * link the presentation was handed, or else the band layout, whose create modal picks up the draft
 * name. Not the presentation itself: the reload empties its field, and creating again from there
 * would clear the draft.
 *
 * @param {{query?: Record<string, unknown>}} route the presentation
 * @param {string} bandIndexPath the band layout's own path
 * @returns {string}
 */
export function bandSpaceCreateReturnPath(route, bandIndexPath) {
  const handed = route?.query?.return_url

  return isSafeReturnUrl(handed) ? handed : bandIndexPath
}

/**
 * A sign-in step (`app_login`, `app_register`, ...) linked from `route`, keeping the way back.
 *
 * @param {string} name
 * @param {{name?: string, fullPath?: string, query?: Record<string, unknown>}} route
 * @returns {{name: string, query?: {return_url: string}}}
 */
export function signInRouteFrom(name, route) {
  return routeReturningTo(name, returnPathOf(route))
}
