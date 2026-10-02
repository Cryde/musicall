/**
 * The patch list editor shows inputs and outputs as two tabs (#1099), so an error on the hidden one
 * has to be pointed at from the tab, or a held or refused save looks like nothing happened.
 */

import { PATCH_LIST_DIRECTIONS } from '../constants/techRiderPatchColumns.js'

/** The directions in tab order, as the editor's `errors` object names them. */
export const PATCH_DIRECTIONS = Object.keys(PATCH_LIST_DIRECTIONS)

/**
 * How many things are wrong in one direction: each list-level message, plus each row with a message.
 *
 * @param {{ list: string[], rows: Object<number, string[]> }} directionErrors
 */
export function patchErrorCount(directionErrors) {
  const rowsInError = Object.values(directionErrors.rows).filter((messages) => messages.length > 0)

  return directionErrors.list.length + rowsInError.length
}

/** The first direction, in tab order, with something wrong, or null. */
export function firstDirectionInError(errors) {
  return PATCH_DIRECTIONS.find((direction) => patchErrorCount(errors[direction]) > 0) ?? null
}

/**
 * The tab to move to so an error is on screen, or null to stay. A tab that has errors of its own
 * is left alone: its messages are already in view.
 */
export function directionToReveal(errors, activeDirection) {
  if (patchErrorCount(errors[activeDirection]) > 0) return null

  return firstDirectionInError(errors)
}
