import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { needsReveal } from './revealTarget.js'

/**
 * On a phone the filters push a search's results below the fold, on a desktop they do not: scroll in
 * the first case only.
 *
 * Run with `npm test`.
 */

describe('needsReveal', () => {
  it('scrolls to results that start below the fold', () => {
    assert.equal(needsReveal(1200, 844), true)
  })

  it('scrolls to results that start in the lower half of the screen', () => {
    assert.equal(needsReveal(600, 844), true)
  })

  it('leaves results already near the top where they are', () => {
    assert.equal(needsReveal(300, 900), false)
  })

  it('scrolls back up to results above the screen', () => {
    assert.equal(needsReveal(-400, 844), true)
  })
})
