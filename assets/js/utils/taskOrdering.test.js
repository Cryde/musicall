import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { orderColumnAfterDrag, toPositions } from './taskOrdering.js'

/**
 * These pin the fix for the reorder corruption: a drag made inside a filtered column used to be
 * persisted as absolute positions covering only the visible cards, which handed them the numbers
 * the hidden cards already held and scrambled the column for every member.
 *
 * Run with `npm test`. Node's own runner, so this costs no dependency. The drag wiring itself
 * still has no coverage, because that would need jsdom and a component harness.
 */

describe('orderColumnAfterDrag', () => {
  describe('with no filter, where every card is visible', () => {
    it('moves a card to the top', () => {
      assert.deepEqual(orderColumnAfterDrag(['a', 'b', 'c'], ['c', 'a', 'b'], 'c'), ['c', 'a', 'b'])
    })

    it('moves a card to the bottom', () => {
      assert.deepEqual(orderColumnAfterDrag(['a', 'b', 'c'], ['b', 'c', 'a'], 'a'), ['b', 'c', 'a'])
    })

    it('returns the same order when the card is dropped where it started', () => {
      assert.deepEqual(orderColumnAfterDrag(['a', 'b', 'c'], ['a', 'b', 'c'], 'b'), ['a', 'b', 'c'])
    })
  })

  describe('with a filter hiding part of the column', () => {
    // The bug this fixes: only a, c and e are on screen. Sending [a, e, c] renumbered 0..2 gave
    // e position 1, which b already held, so the column ordered on a tie-break afterwards.
    it('produces an ordering covering the hidden cards, not just the visible subset', () => {
      const ordering = orderColumnAfterDrag(['a', 'b', 'c', 'd', 'e'], ['a', 'e', 'c'], 'e')

      assert.deepEqual(ordering, ['a', 'b', 'e', 'c', 'd'])
      assert.deepEqual([...ordering].sort(), ['a', 'b', 'c', 'd', 'e'])
    })

    it('stops the moved card above the first visible card, not above the hidden ones', () => {
      assert.deepEqual(orderColumnAfterDrag(['a', 'b', 'c', 'd'], ['c', 'b'], 'c'), [
        'a',
        'c',
        'b',
        'd'
      ])
    })

    it('stops the moved card below the last visible card, not below the hidden ones', () => {
      assert.deepEqual(orderColumnAfterDrag(['a', 'b', 'c', 'd'], ['c', 'a'], 'a'), [
        'b',
        'c',
        'a',
        'd'
      ])
    })

    it('writes the column unchanged when the card was dropped back where it started', () => {
      assert.deepEqual(orderColumnAfterDrag(['a', 'b', 'c', 'd'], ['b', 'd'], 'b'), [
        'a',
        'b',
        'c',
        'd'
      ])
    })
  })

  describe('across columns', () => {
    it('splices the incoming card into the full destination column', () => {
      assert.deepEqual(orderColumnAfterDrag(['x', 'y', 'z'], ['x', 'm', 'z'], 'm'), [
        'x',
        'm',
        'y',
        'z'
      ])
    })

    it('appends the incoming card when it was dropped below every visible card', () => {
      assert.deepEqual(orderColumnAfterDrag(['x', 'y', 'z'], ['x', 'z', 'm'], 'm'), [
        'x',
        'y',
        'z',
        'm'
      ])
    })

    it('handles an empty destination column', () => {
      assert.deepEqual(orderColumnAfterDrag([], ['m'], 'm'), ['m'])
    })
  })

  it('leaves the column untouched when the moved card is not in the visible order', () => {
    assert.deepEqual(orderColumnAfterDrag(['a', 'b'], ['a', 'b'], 'ghost'), ['a', 'b'])
  })

  it('ignores a visible neighbour the column no longer holds', () => {
    assert.deepEqual(orderColumnAfterDrag(['a', 'b', 'c'], ['gone', 'c', 'a'], 'c'), [
      'c',
      'a',
      'b'
    ])
  })

  it('does not mutate its inputs', () => {
    const fullOrderedIds = ['a', 'b', 'c']
    const visibleOrderedIds = ['c', 'a']

    orderColumnAfterDrag(fullOrderedIds, visibleOrderedIds, 'c')

    assert.deepEqual(fullOrderedIds, ['a', 'b', 'c'])
    assert.deepEqual(visibleOrderedIds, ['c', 'a'])
  })
})

describe('toPositions', () => {
  // The server rejects anything that is not a contiguous 0..n-1 sequence, so the index is the
  // position and nothing is carried over from the task's previous number.
  it('numbers the column from zero without a gap', () => {
    assert.deepEqual(toPositions(['a', 'b', 'c']), [
      { id: 'a', position: 0 },
      { id: 'b', position: 1 },
      { id: 'c', position: 2 }
    ])
  })

  it('returns an empty payload for an empty column', () => {
    assert.deepEqual(toPositions([]), [])
  })
})
