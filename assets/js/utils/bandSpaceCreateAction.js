import { bandSpaceCreateReturnPath } from './unauthenticatedRedirect.js'

/**
 * Creating a Band Space, kept across sign-in as a pending action (#1304). Only the name: the create dialog takes
 * nothing else, so the size and needs picked on the presentation stay there.
 */
export const BAND_SPACE_CREATE_ACTION = 'band_space.create'

/**
 * The pending action of a visitor creating a space from `route`. It resumes where they come back to: the band
 * layout, whose dialog reopens on its own, or a deep link the page was handed, where it is only offered, since
 * the member came back for that page.
 *
 * @param {string} name what the visitor typed, possibly nothing
 * @param {{query?: Record<string, unknown>}} route the page the visitor started on
 * @param {string} bandIndexPath the band layout's own path
 * @returns {{kind: string, route: string, payload: {name: string}, stoppedOnSubmit: boolean}}
 */
export function bandSpaceCreateAction(name, route, bandIndexPath) {
  const returnPath = bandSpaceCreateReturnPath(route, bandIndexPath)

  return {
    kind: BAND_SPACE_CREATE_ACTION,
    route: returnPath,
    payload: { name: name.trim() },
    stoppedOnSubmit: returnPath === bandIndexPath
  }
}

/** The name to prefill from a kept payload, which comes back from storage and so is checked again. */
export function bandSpaceCreateName(payload) {
  return typeof payload?.name === 'string' ? payload.name : ''
}
