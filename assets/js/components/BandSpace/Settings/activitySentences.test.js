import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
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

const agenda = (type, payload) => ({ module: 'agenda', type, payload })

// West of UTC is where reading a bare day as an instant would show the day before.
for (const timeZone of ['Europe/Paris', 'America/Martinique']) {
  describe(`agenda date sentences in ${timeZone} (#1162)`, () => {
    const original = process.env.TZ
    before(() => {
      process.env.TZ = timeZone
    })
    after(() => {
      process.env.TZ = original
    })

    it('name a cancelled date by the day members saw, not its UTC key', () => {
      assert.equal(
        activitySentence(
          agenda('occurrence_cancelled', {
            title: 'Test minuit',
            occurrence_date: '2026-10-11',
            occurrence_civil_date: '2026-10-12'
          })
        ),
        'a annulé la date du 12 octobre 2026 de « Test minuit »'
      )
    })

    it('fall back to the UTC key on a cancellation recorded before the civil date existed', () => {
      assert.equal(
        activitySentence(
          agenda('occurrence_cancelled', { title: 'Répétition', occurrence_date: '2026-06-15' })
        ),
        'a annulé la date du 15 juin 2026 de « Répétition »'
      )
    })

    it('spell out the day a series was truncated from', () => {
      assert.equal(
        activitySentence(
          agenda('series_truncated', { title: 'Test minuit', from_occurrence_date: '2026-10-13' })
        ),
        'a tronqué la série « Test minuit » à partir du 13 octobre 2026'
      )
    })

    it('keep a placeholder when the payload has no date', () => {
      assert.equal(
        activitySentence(agenda('series_truncated', { title: 'Test minuit' })),
        'a tronqué la série « Test minuit » à partir du ?'
      )
    })
  })
}
