import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  INVITATION_REFUSAL,
  invitationRefusalOnAnswer,
  invitationRefusalOnOpen,
  invitationTokenInReturnUrl,
  registerRouteFromLogin
} from './bandSpaceInvitation.js'

describe('invitationTokenInReturnUrl', () => {
  it('reads the token of the invitation page', () => {
    assert.equal(invitationTokenInReturnUrl('/band/invitation/abc123'), 'abc123')
  })

  it('stops at a query string or a fragment', () => {
    assert.equal(invitationTokenInReturnUrl('/band/invitation/abc123?x=1'), 'abc123')
    assert.equal(invitationTokenInReturnUrl('/band/invitation/abc123#top'), 'abc123')
  })

  it('ignores any other page', () => {
    assert.equal(invitationTokenInReturnUrl('/band/42/agenda'), null)
    assert.equal(invitationTokenInReturnUrl('/band/invitation/'), null)
    assert.equal(invitationTokenInReturnUrl('/band/invitation/abc/extra'), null)
    assert.equal(invitationTokenInReturnUrl('/other/band/invitation/abc'), null)
    assert.equal(invitationTokenInReturnUrl('https://evil.example/band/invitation/abc'), null)
  })

  it('tolerates no return url at all', () => {
    assert.equal(invitationTokenInReturnUrl(null), null)
    assert.equal(invitationTokenInReturnUrl(undefined), null)
    assert.equal(invitationTokenInReturnUrl(['/band/invitation/abc']), null)
  })
})

describe('registerRouteFromLogin', () => {
  // The bug of #1359: login forwarded return_url only, so register showed neither the band nor the email.
  it('forwards the invitation token alongside the way back to the invitation page', () => {
    assert.deepEqual(registerRouteFromLogin('/band/invitation/abc123'), {
      name: 'app_register',
      query: { return_url: '/band/invitation/abc123', invitation_token: 'abc123' }
    })
  })

  it('forwards any other way back unchanged', () => {
    assert.deepEqual(registerRouteFromLogin('/messages'), {
      name: 'app_register',
      query: { return_url: '/messages' }
    })
  })

  it('sends an empty query when the login form has no way back', () => {
    assert.deepEqual(registerRouteFromLogin(null), { name: 'app_register', query: {} })
  })
})

describe('invitationRefusalOnOpen', () => {
  it('tells a member they are already in the band, from the 409 of the info call', () => {
    assert.equal(invitationRefusalOnOpen(409), INVITATION_REFUSAL.ALREADY_MEMBER)
  })

  it('reads a 404 as an invitation that expired, was cancelled or already used', () => {
    assert.equal(invitationRefusalOnOpen(404), INVITATION_REFUSAL.NO_LONGER_VALID)
  })

  it('leaves anything else to the raw message', () => {
    assert.equal(invitationRefusalOnOpen(500), null)
    assert.equal(invitationRefusalOnOpen(undefined), null)
  })
})

describe('invitationRefusalOnAnswer', () => {
  it('reads a 403 as an invitation addressed to another account', () => {
    assert.equal(invitationRefusalOnAnswer(403), INVITATION_REFUSAL.OTHER_RECIPIENT)
  })

  it('reads a 404 as an invitation no longer valid', () => {
    assert.equal(invitationRefusalOnAnswer(404), INVITATION_REFUSAL.NO_LONGER_VALID)
  })

  // A 409 on accept is also a space pending deletion: that message stays the API's own.
  it('leaves a 409 and anything else to the raw message', () => {
    assert.equal(invitationRefusalOnAnswer(409), null)
    assert.equal(invitationRefusalOnAnswer(500), null)
    assert.equal(invitationRefusalOnAnswer(undefined), null)
  })
})
