/**
 * Which rows a click leaves selected in the file list.
 *
 * Extracted from the component because it is the only part of a multi-select worth testing, and
 * because there is no component test harness here: the anchor bookkeeping and the range arithmetic
 * are easy to get subtly wrong in ways nobody notices until a member trashes the wrong photos.
 *
 * The model is the one every file manager uses:
 *   - a checkbox, or Ctrl/Cmd+click, toggles one row and moves the anchor to it
 *   - Shift+click selects the whole run between the anchor and the row clicked, anchor unchanged
 *   - a bare click is not selection at all, it opens the file, so it never reaches here
 *
 * Run with `npm test`.
 */

/**
 * The inclusive run between two rows, in rendered order, whichever of the two comes first.
 *
 * @param {string[]} orderedIds ids in the order the list renders them
 * @param {string} fromId
 * @param {string} toId
 * @returns {string[]} empty when either row is not on screen
 */
export function rangeBetween(orderedIds, fromId, toId) {
  const from = orderedIds.indexOf(fromId)
  const to = orderedIds.indexOf(toId)

  if (from === -1 || to === -1) {
    return []
  }

  const [start, end] = from <= to ? [from, to] : [to, from]

  return orderedIds.slice(start, end + 1)
}

/**
 * The selection and anchor after a click on one row.
 *
 * Shift without an anchor, or with an anchor that has since scrolled out of the loaded rows, falls
 * back to a plain toggle rather than selecting nothing: a member holding Shift always means to
 * select something.
 *
 * @param {object} params
 * @param {string} params.id row that was clicked
 * @param {string[]} params.orderedIds ids in the order the list renders them
 * @param {string[]} params.selected currently selected ids
 * @param {?string} params.anchorId row the last toggle happened on
 * @param {boolean} [params.shift]
 * @returns {{selected: string[], anchorId: string}}
 */
export function selectionAfterClick({ id, orderedIds, selected, anchorId, shift = false }) {
  const current = [...selected]

  if (shift && anchorId) {
    const range = rangeBetween(orderedIds, anchorId, id)
    if (range.length > 0) {
      // The anchor stays put, so dragging the shift-click up and down redraws one run instead of
      // walking the anchor along behind it.
      return { selected: range, anchorId }
    }
  }

  return {
    selected: current.includes(id)
      ? current.filter((selectedId) => selectedId !== id)
      : [...current, id],
    anchorId: id
  }
}

/**
 * The selection with everything no longer on screen dropped.
 *
 * The list pages, and a bulk action removes rows, so a selection outlives the rows it names. Acting
 * on an id the member can no longer see is the one outcome this has to prevent.
 *
 * @param {string[]} selected
 * @param {string[]} availableIds
 * @returns {string[]}
 */
export function prunedSelection(selected, availableIds) {
  const available = new Set(availableIds)

  return [...selected].filter((id) => available.has(id))
}

/**
 * Whether the header checkbox should read as ticked: every row on screen is selected, and there is
 * at least one. An empty list is not "all selected".
 *
 * @param {string[]} orderedIds
 * @param {string[]} selected
 * @returns {boolean}
 */
export function areAllSelected(orderedIds, selected) {
  if (orderedIds.length === 0) {
    return false
  }

  const chosen = new Set(selected)

  return orderedIds.every((id) => chosen.has(id))
}
