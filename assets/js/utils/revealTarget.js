/**
 * Whether scrolling to a search's results is worth it: only when their top is off screen or low
 * enough that the list starts below the fold. On a desktop the filters sit just above the results and
 * this is false, so nothing moves under the visitor's cursor.
 */
export function needsReveal(targetTop, viewportHeight) {
  return targetTop < 0 || targetTop > viewportHeight / 2
}
