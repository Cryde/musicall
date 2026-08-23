/**
 * Where to send a visitor who is not signed in, for a route that requires it.
 *
 * A route may name its own landing place through `meta.unauthenticatedRedirect`, so a shared link to
 * a gated module can explain itself instead of presenting a bare password box. Anything without one
 * goes to the login form.
 *
 * Only the login form receives `return_url`. It is the one destination that knows how to resume the
 * journey, and without it an invitation link is a dead end: its token lives in the path, so the
 * redirect discards it. A destination that is a page rather than a form has nothing to resume with.
 *
 * Extracted from the guard in App.vue because this branch carries two deliberate carve-outs now, the
 * marketing landing page and this one, and it is the kind of conditional a third change gets wrong.
 *
 * @param {{meta?: {unauthenticatedRedirect?: string}, fullPath?: string}} to a resolved route
 * @returns {{name: string, query?: {return_url: string}}}
 */
export function resolveUnauthenticatedRedirect(to) {
  const destination = to?.meta?.unauthenticatedRedirect ?? 'app_login'

  if (destination !== 'app_login' || !to?.fullPath) {
    return { name: destination }
  }

  return { name: destination, query: { return_url: to.fullPath } }
}
