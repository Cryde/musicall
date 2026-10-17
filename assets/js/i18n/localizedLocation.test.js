import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { localizedLocation } from './localizedLocation.js'

describe('localizedLocation', () => {
  it('has no other language version of a page until the /en routes exist', () => {
    const route = { name: 'app_publications', path: '/publications', params: {}, query: {} }

    assert.equal(localizedLocation(route, 'en'), null)
    assert.equal(localizedLocation(route, 'fr'), null)
  })
})
