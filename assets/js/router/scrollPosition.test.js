import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { keepsScrollPosition } from './scrollPosition.js'

/**
 * Changing a search filter rewrites the query string, and the router scrolled to the top on each
 * one. These pin what counts as "the same page": a filter change stays put, anything else still
 * starts at the top.
 *
 * Run with `npm test`.
 */

function location(path, query = {}, hash = '') {
  return { path, query, hash }
}

describe('keepsScrollPosition', () => {
  it('stays put when only a filter changes', () => {
    const from = location('/rechercher-un-musicien', { type: '1' })
    const to = location('/rechercher-un-musicien', { type: '1', instrument: 'guitare' })

    assert.equal(keepsScrollPosition(to, from), true)
  })

  it('goes back to the top on a new page of results', () => {
    const from = location('/publications', { page: '1' })
    const to = location('/publications', { page: '2' })

    assert.equal(keepsScrollPosition(to, from), false)
  })

  it('goes back to the top on another page of the site', () => {
    assert.equal(keepsScrollPosition(location('/forum'), location('/publications')), false)
  })

  it('lets a hash change scroll to its anchor', () => {
    assert.equal(keepsScrollPosition(location('/cgu', {}, '#article-3'), location('/cgu')), false)
  })
})
