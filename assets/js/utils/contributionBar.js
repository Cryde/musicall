/**
 * The width of a member's contribution bar in the finance sidebar.
 *
 * Extracted because the bar rendered at zero width for every band since the feature shipped, next to
 * amounts that were correct, so it read as "nobody has contributed". The cause was arithmetic on a
 * field the API does not send: `undefined` produces NaN, NaN is not equal to 0 so it walked straight
 * through the caller's `=== 0` guard, and `width: NaN%` is simply ignored by the browser.
 *
 * Nothing here trusts its input for that reason, and it lives outside the component because there is
 * no component test harness in this project: a percentage is arithmetic and can be pinned on its own.
 */

/**
 * The largest contribution in the set, or 0 when there is nothing usable to compare against.
 *
 * @param {Array<{total?: number}>} contributions as the API returns them, so `total`, not `amount`
 * @returns {number}
 */
export function largestContribution(contributions) {
  const totals = (contributions ?? [])
    .map((contribution) => contribution?.total)
    .filter((total) => Number.isFinite(total))

  return totals.length > 0 ? Math.max(...totals) : 0
}

/**
 * A member's share of the largest contribution, 0 to 100. Anything unusable reads as no contribution
 * rather than as a broken style attribute.
 *
 * @param {number} total this member's contribution
 * @param {number} largest the largest contribution in the set
 * @returns {number}
 */
export function contributionPercent(total, largest) {
  // Both sides are guarded, not just the divisor. A split is validated as strictly positive and has
  // no update endpoint, so a negative total cannot reach here today, but a function whose whole
  // reason to exist is distrusting its input should not have one edge it happens to trust.
  if (!Number.isFinite(total) || !Number.isFinite(largest) || total <= 0 || largest <= 0) {
    return 0
  }

  return Math.round((total / largest) * 100)
}
