/**
 * Whether a click on a link is followed in this tab, by the test vue-router's own links make: with a
 * modifier key, another mouse button or a target of `_blank`, the page opens elsewhere instead.
 */
export function followsInPlace(event, target = null) {
  if (event.metaKey || event.altKey || event.ctrlKey || event.shiftKey) {
    return false
  }
  if (event.button !== undefined && event.button !== 0) {
    return false
  }
  return !/\b_blank\b/i.test(target ?? '')
}
