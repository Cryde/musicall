import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { resolveUnauthenticatedRedirect } from './unauthenticatedRedirect.js'

describe('resolveUnauthenticatedRedirect', () => {
  it('sends a route with no preference to the login form, carrying the destination', () => {
    assert.deepEqual(resolveUnauthenticatedRedirect({ meta: {}, fullPath: '/messages' }), {
      name: 'app_login',
      query: { return_url: '/messages' }
    })
  })

  // The whole point of #915: the token lives in the path, so losing it strands the visitor.
  it('keeps an invitation token', () => {
    const result = resolveUnauthenticatedRedirect({
      meta: { unauthenticatedRedirect: 'app_login' },
      fullPath: '/band/invitation/abc123'
    })
    assert.equal(result.query.return_url, '/band/invitation/abc123')
  })

  it('keeps the query string, not just the path', () => {
    const result = resolveUnauthenticatedRedirect({
      meta: {},
      fullPath: '/band/1/tech-riders?rider=42'
    })
    assert.equal(result.query.return_url, '/band/1/tech-riders?rider=42')
  })

  // A landing page has no form to resume from, so a return_url there would be dead weight.
  it('does not attach a destination when the route names a page instead of the login form', () => {
    assert.deepEqual(
      resolveUnauthenticatedRedirect({
        meta: { unauthenticatedRedirect: 'app_band_space_presentation' },
        fullPath: '/band/1/agenda'
      }),
      { name: 'app_band_space_presentation' }
    )
  })

  it('omits the query rather than sending an empty one when there is no path', () => {
    assert.deepEqual(resolveUnauthenticatedRedirect({ meta: {} }), { name: 'app_login' })
  })

  it('tolerates a route with no meta at all', () => {
    assert.deepEqual(resolveUnauthenticatedRedirect({ fullPath: '/x' }), {
      name: 'app_login',
      query: { return_url: '/x' }
    })
    assert.deepEqual(resolveUnauthenticatedRedirect(undefined), { name: 'app_login' })
  })
})
