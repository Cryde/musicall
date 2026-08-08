import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { contributionPercent, largestContribution } from './contributionBar.js'

/**
 * The bug these exist for: the sidebar read `amount` off a payload whose field is `total`, so every
 * bar was `width: NaN%` and rendered at zero next to a correct amount. The assertions below pin both
 * halves, the field the API actually sends and the refusal to emit NaN whatever it is handed.
 *
 * Run with `npm test`.
 */

const CONTRIBUTIONS = [
  { member_id: 'a', name: 'Marie', total: 12000 },
  { member_id: 'b', name: 'Julien', total: 30000 },
  { member_id: 'c', name: 'Sam', total: 6000 }
]

describe('largestContribution', () => {
  it('finds the biggest contribution of the set', () => {
    assert.equal(largestContribution(CONTRIBUTIONS), 30000)
  })

  it('reads total, which is the field the API sends', () => {
    // The whole bug in one case: an `amount` key is not a contribution, so it counts for nothing
    // rather than poisoning the maximum with undefined.
    assert.equal(largestContribution([{ member_id: 'a', amount: 12000 }]), 0)
  })

  it('has no contribution to compare against when the list is empty or absent', () => {
    assert.equal(largestContribution([]), 0)
    assert.equal(largestContribution(undefined), 0)
    assert.equal(largestContribution(null), 0)
  })

  it('ignores entries carrying an unusable total', () => {
    assert.equal(
      largestContribution([
        { total: undefined },
        { total: null },
        { total: 'beaucoup' },
        { total: 900 }
      ]),
      900
    )
    assert.equal(largestContribution([{ total: Number.NaN }]), 0)
  })
})

describe('contributionPercent', () => {
  it('scales a contribution against the largest one', () => {
    const largest = largestContribution(CONTRIBUTIONS)

    assert.equal(contributionPercent(30000, largest), 100)
    assert.equal(contributionPercent(6000, largest), 20)
    assert.equal(contributionPercent(12000, largest), 40)
  })

  it('rounds to a whole percent', () => {
    assert.equal(contributionPercent(1, 3), 33)
    assert.equal(contributionPercent(2, 3), 67)
  })

  it('never returns NaN, whatever it is handed', () => {
    // A NaN width is not a visible failure: the browser drops the declaration and the bar sits at
    // zero, which is how this shipped unnoticed.
    for (const [total, largest] of [
      [undefined, 30000],
      [30000, undefined],
      [Number.NaN, Number.NaN],
      ['12000', 30000],
      [null, null]
    ]) {
      assert.equal(contributionPercent(total, largest), 0)
    }
  })

  it('treats a zero or negative maximum as nothing to scale against', () => {
    assert.equal(contributionPercent(0, 0), 0)
    assert.equal(contributionPercent(500, 0), 0)
    assert.equal(contributionPercent(500, -100), 0)
  })

  it('draws no bar for a contribution of zero or less', () => {
    // Unreachable while a split is validated as strictly positive and has no update endpoint, but a
    // negative width is not a width, and the guard should not be asymmetric.
    assert.equal(contributionPercent(0, 30000), 0)
    assert.equal(contributionPercent(-500, 30000), 0)
  })
})
