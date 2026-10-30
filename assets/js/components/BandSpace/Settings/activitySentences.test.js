import assert from 'node:assert/strict'
import { after, afterEach, before, describe, it } from 'node:test'
import { i18n } from '../../../i18n/index.js'
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

const finance = (type, payload) => ({ module: 'finance', type, payload })

describe('finance activity sentences', () => {
  it('write amounts in euros the French way', () => {
    assert.equal(
      activitySentence(finance('entry_created', { label: 'Cachet', amount: 123450 })),
      "a créé l'entrée « Cachet » (1\u202f234,50\u00a0€)"
    )
    assert.equal(
      activitySentence(finance('entry_amount_changed', { from: 5000, to: 7500 })),
      'a changé le montant de 50,00\u00a0€ à 75,00\u00a0€'
    )
  })

  describe('with a payload that names nothing', () => {
    afterEach(() => {
      i18n.global.locale.value = 'fr'
    })

    it('stand in for the name with an em dash in French', () => {
      assert.equal(activitySentence(finance('entry_deleted', {})), "a supprimé l'entrée « — »")
    })

    // The wording comes from the catalogue: only the stand-in is this module's.
    it('stand in for the name and the amount with a hyphen in English', () => {
      i18n.global.locale.value = 'en'
      const sentences = [
        activitySentence(finance('entry_deleted', {})),
        activitySentence(finance('entry_amount_changed', { from: null, to: 7500 }))
      ]

      for (const sentence of sentences) {
        assert.ok(!sentence.includes('—'), sentence)
        assert.ok(sentence.includes('-'), sentence)
      }
    })
  })
})

const setlist = (type, payload) => ({ module: 'setlist', type, payload })

describe('lyrics activity sentences (#1260)', () => {
  it('name the song only when the lyrics were written for one', () => {
    assert.equal(
      activitySentence(setlist('lyrics_added', { title: 'Idée', song_title: null })),
      'a écrit les paroles « Idée »'
    )
    assert.equal(
      activitySentence(setlist('lyrics_added', { title: 'Idée', song_title: 'Enfin' })),
      'a écrit les paroles « Idée » pour la chanson « Enfin »'
    )
  })

  it('name both the lyrics and the song on a link and an unlink', () => {
    assert.equal(
      activitySentence(setlist('lyrics_linked', { title: 'Idée', song_title: 'Enfin' })),
      'a lié les paroles « Idée » à la chanson « Enfin »'
    )
    assert.equal(
      activitySentence(setlist('lyrics_unlinked', { title: 'Idée', song_title: 'Enfin' })),
      'a délié les paroles « Idée » de la chanson « Enfin »'
    )
  })

  it('read the edit, archive and restore by title', () => {
    assert.deepEqual(
      ['lyrics_updated', 'lyrics_archived', 'lyrics_restored'].map((type) =>
        activitySentence(setlist(type, { title: 'Idée' }))
      ),
      [
        'a modifié les paroles « Idée »',
        'a mis les paroles « Idée » à la corbeille',
        'a restauré les paroles « Idée »'
      ]
    )
  })
})
