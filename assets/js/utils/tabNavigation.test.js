import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { adjacentTabKey, centeredScrollLeft } from './tabNavigation.js'

const TABS = [{ key: 'a' }, { key: 'b' }, { key: 'c' }]

describe('adjacentTabKey', () => {
  it('moves right', () => {
    assert.equal(adjacentTabKey(TABS, 'a', 1), 'b')
    assert.equal(adjacentTabKey(TABS, 'b', 1), 'c')
  })

  it('moves left', () => {
    assert.equal(adjacentTabKey(TABS, 'c', -1), 'b')
    assert.equal(adjacentTabKey(TABS, 'b', -1), 'a')
  })

  it('wraps from the last tab to the first', () => {
    assert.equal(adjacentTabKey(TABS, 'c', 1), 'a')
  })

  // A bare modulo returns -1 here, which is the whole reason this is a function and not inline.
  it('wraps from the first tab to the last rather than going negative', () => {
    assert.equal(adjacentTabKey(TABS, 'a', -1), 'c')
  })

  it('keeps the current key when it is not in the list', () => {
    assert.equal(adjacentTabKey(TABS, 'missing', 1), 'missing')
  })

  it('keeps the current key when the list is empty', () => {
    assert.equal(adjacentTabKey([], 'a', 1), 'a')
  })

  it('is a no-op on a single tab, in both directions', () => {
    assert.equal(adjacentTabKey([{ key: 'only' }], 'only', 1), 'only')
    assert.equal(adjacentTabKey([{ key: 'only' }], 'only', -1), 'only')
  })
})

describe('centeredScrollLeft', () => {
  it('scrolls an item out of sight on the right to the middle of the row', () => {
    // A 100px tab 455px from the left edge of a 340px row: its middle has to move to 170.
    assert.equal(
      centeredScrollLeft({ scrollLeft: 0, itemOffset: 455, itemWidth: 100, viewportWidth: 340 }),
      335
    )
  })

  it('counts from where the row is already scrolled', () => {
    assert.equal(
      centeredScrollLeft({ scrollLeft: 200, itemOffset: 255, itemWidth: 100, viewportWidth: 340 }),
      335
    )
  })

  it('scrolls back for an item hidden on the left', () => {
    assert.equal(
      centeredScrollLeft({ scrollLeft: 335, itemOffset: -120, itemWidth: 100, viewportWidth: 340 }),
      95
    )
  })

  it('leaves a centred item where it is', () => {
    assert.equal(
      centeredScrollLeft({ scrollLeft: 50, itemOffset: 120, itemWidth: 100, viewportWidth: 340 }),
      50
    )
  })

  // The browser clamps a negative scrollLeft to 0; the first tab is that case.
  it('returns a negative value for the first item, left to the browser to clamp', () => {
    assert.ok(
      centeredScrollLeft({ scrollLeft: 0, itemOffset: 10, itemWidth: 100, viewportWidth: 340 }) < 0
    )
  })
})
