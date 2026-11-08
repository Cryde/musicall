import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  bandSpaceCreateReturnPath,
  loginRouteReturningTo,
  resolveSignedInRedirect,
  resolveUnauthenticatedRedirect,
  returnPathOf,
  routeReturningTo,
  signInRouteFrom
} from './unauthenticatedRedirect.js'

// What an expired session pushes (#1300). The login form only reads `return_url`, so any other key
// sent the member back to the home page after signing in again.
describe('loginRouteReturningTo', () => {
  it('hands the login form the page the member was on, under the key it reads', () => {
    assert.deepEqual(loginRouteReturningTo('/band/1/agenda?view=month'), {
      name: 'app_login',
      query: { return_url: '/band/1/agenda?view=month' }
    })
  })

  it('omits the query rather than sending an empty one when there is no path', () => {
    assert.deepEqual(loginRouteReturningTo(undefined), { name: 'app_login' })
    assert.deepEqual(loginRouteReturningTo(''), { name: 'app_login' })
  })
})

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

  // #1302: the presentation is still shown, but a visitor who signs in from it gets back to the module.
  it('tells a landing page where the visitor was going too', () => {
    assert.deepEqual(
      resolveUnauthenticatedRedirect({
        meta: { unauthenticatedRedirect: 'app_band_space_presentation' },
        fullPath: '/band/1/agenda'
      }),
      { name: 'app_band_space_presentation', query: { return_url: '/band/1/agenda' } }
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

describe('resolveSignedInRedirect', () => {
  // Signed in in another tab, then back on a login form that was told where to go.
  it('follows the return a sign-in step was handed', () => {
    assert.equal(
      resolveSignedInRedirect({ query: { return_url: '/band/1/agenda?view=month' } }),
      '/band/1/agenda?view=month'
    )
  })

  it('goes home when there is no return', () => {
    assert.deepEqual(resolveSignedInRedirect({ query: {} }), { name: 'app_home' })
    assert.deepEqual(resolveSignedInRedirect({}), { name: 'app_home' })
  })

  it('goes home rather than following a return that leaves the site', () => {
    for (const returnUrl of [
      'https://evil.example',
      '//evil.example',
      '/\\evil.example',
      '/\t/evil.example',
      ['/messages']
    ]) {
      assert.deepEqual(resolveSignedInRedirect({ query: { return_url: returnUrl } }), {
        name: 'app_home'
      })
    }
  })
})

describe('routeReturningTo', () => {
  it('adds the return to what the route already needs', () => {
    assert.deepEqual(
      routeReturningTo('app_verify_email', '/messages', { email: 'a@example.com' }),
      {
        name: 'app_verify_email',
        query: { email: 'a@example.com', return_url: '/messages' }
      }
    )
  })

  it('keeps what the route needs when there is no return', () => {
    assert.deepEqual(routeReturningTo('app_verify_email', null, { email: 'a@example.com' }), {
      name: 'app_verify_email',
      query: { email: 'a@example.com' }
    })
  })

  // Validated at each hop: a crafted return_url on one page is not handed on to the next.
  it('drops a return that leaves the site', () => {
    for (const returnUrl of [
      'https://evil.example',
      '//evil.example',
      '/\\evil.example',
      '/\n/evil.example',
      ['/x'],
      42
    ]) {
      assert.deepEqual(routeReturningTo('app_login', returnUrl), { name: 'app_login' })
    }
  })
})

describe('returnPathOf', () => {
  it('is the page the visitor is on', () => {
    assert.equal(
      returnPathOf({
        name: 'app_forum_topic',
        fullPath: '/forums/topic/abc?page=2',
        query: { page: '2' }
      }),
      '/forums/topic/abc?page=2'
    )
  })

  // The presentation a Band Space deep link landed on, or a login form sent from anywhere.
  it('forwards the return the page was handed rather than its own address', () => {
    assert.equal(
      returnPathOf({
        name: 'app_band_space_presentation',
        fullPath: '/band-space?return_url=%2Fband%2F1%2Fagenda',
        query: { return_url: '/band/1/agenda' }
      }),
      '/band/1/agenda'
    )
    assert.equal(
      returnPathOf({
        name: 'app_login',
        fullPath: '/login?return_url=%2Fmessages',
        query: { return_url: '/messages' }
      }),
      '/messages'
    )
  })

  it('is nothing on a sign-in step that was handed no return', () => {
    for (const name of [
      'app_login',
      'app_register',
      'app_verify_email',
      'app_forgot_password',
      'app_reset_password'
    ]) {
      assert.equal(returnPathOf({ name, fullPath: '/somewhere', query: {} }), null, name)
    }
  })

  it('falls back to the page itself when the return it was handed is unsafe', () => {
    assert.equal(
      returnPathOf({
        name: 'app_home',
        fullPath: '/?return_url=%2F%2Fevil.example',
        query: { return_url: '//evil.example' }
      }),
      '/?return_url=%2F%2Fevil.example'
    )
    assert.equal(
      returnPathOf({
        name: 'app_login',
        fullPath: '/login',
        query: { return_url: '//evil.example' }
      }),
      null
    )
  })

  it('tolerates a missing route', () => {
    assert.equal(returnPathOf(undefined), null)
  })
})

describe('signInRouteFrom', () => {
  it('links a sign-in step from a page, carrying that page', () => {
    assert.deepEqual(
      signInRouteFrom('app_register', { name: 'app_discover', fullPath: '/decouvrir', query: {} }),
      {
        name: 'app_register',
        query: { return_url: '/decouvrir' }
      }
    )
  })

  it('links one sign-in step from another, forwarding the return', () => {
    assert.deepEqual(
      signInRouteFrom('app_forgot_password', {
        name: 'app_login',
        fullPath: '/login?return_url=%2Fmessages',
        query: { return_url: '/messages' }
      }),
      { name: 'app_forgot_password', query: { return_url: '/messages' } }
    )
    assert.deepEqual(
      signInRouteFrom('app_login', {
        name: 'app_forgot_password',
        fullPath: '/forgot-password',
        query: {}
      }),
      {
        name: 'app_login'
      }
    )
  })
})

describe('bandSpaceCreateReturnPath', () => {
  it('forwards the deep link the presentation was handed', () => {
    assert.equal(
      bandSpaceCreateReturnPath({ query: { return_url: '/band/1/agenda?view=month' } }, '/band'),
      '/band/1/agenda?view=month'
    )
  })

  // The presentation itself would come back with an empty field, and creating again would clear the draft.
  it('goes to the band layout, never back to the presentation, when it was handed nothing', () => {
    assert.equal(bandSpaceCreateReturnPath({ query: {} }, '/band'), '/band')
    assert.equal(bandSpaceCreateReturnPath({}, '/band'), '/band')
  })

  it('goes to the band layout rather than following a return that leaves the site', () => {
    for (const returnUrl of [
      'https://evil.example',
      '//evil.example',
      '/\\evil.example',
      ['/band/1']
    ]) {
      assert.equal(
        bandSpaceCreateReturnPath({ query: { return_url: returnUrl } }, '/band'),
        '/band'
      )
    }
  })
})
