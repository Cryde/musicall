import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { adjacentTabKey } from './tabNavigation.js'

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
