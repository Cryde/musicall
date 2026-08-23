/**
 * The tab a left or right arrow key should move to, wrapping at both ends.
 *
 * Extracted from the component because it is the only part of a tablist worth testing: the
 * wraparound is index arithmetic that is easy to get subtly wrong (a bare modulo returns a negative
 * index when stepping left off the first tab), and there is no component test harness in this project.
 *
 * Returns the current key unchanged when it is not in the list, so a caller cannot land on undefined.
 *
 * @param {ReadonlyArray<{key: string}>} items
 * @param {string} currentKey
 * @param {number} step -1 for left, 1 for right
 * @returns {string}
 */
export function adjacentTabKey(items, currentKey, step) {
  if (!items.length) {
    return currentKey
  }

  const index = items.findIndex((item) => item.key === currentKey)
  if (index === -1) {
    return currentKey
  }

  return items[(index + step + items.length) % items.length].key
}
