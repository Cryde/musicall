import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { activitySentence } from './activitySentences.js'

const invitation = (type, payload) => ({ module: 'settings', type, payload })

describe('invitation activity sentences', () => {
  it('name an invitation by the address the inviter typed', () => {
    assert.equal(
      activitySentence(invitation('invitation_sent', { email: 'a***@example.com' })),
      'a invité a***@example.com'
    )
  })

  it('name an invitation made by username by that username, never an address (#1119)', () => {
    assert.equal(
      activitySentence(invitation('invitation_sent', { invited_username: 'bassiste' })),
      'a invité @bassiste'
    )
    assert.equal(
      activitySentence(invitation('invitation_declined', { invited_username: 'bassiste' })),
      "a refusé l'invitation pour @bassiste"
    )
  })

  it('fall back when the payload names nobody', () => {
    assert.equal(
      activitySentence(invitation('invitation_expired', {})),
      "l'invitation pour un utilisateur a expiré"
    )
  })
})
