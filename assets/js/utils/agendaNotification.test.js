import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
import en from '../i18n/en/index.js'
import { i18n } from '../i18n/index.js'
import { agendaEntryCreatedPreview } from './agendaNotification.js'

/** A band_space_agenda_entry_created payload as the feed returns it. */
function payload(fields) {
  return {
    entry_title: 'Répétition',
    event_datetime: '2027-01-06T19:00:00+01:00',
    is_all_day: false,
    recurrence_frequency: 'weekly',
    recurrence_until_date: '2027-06-30',
    ...fields
  }
}

// Each case runs east and west of UTC: an all day start is pinned to midnight UTC and the last day is a
// bare date, and either one read as an instant lands a day early in Martinique.
for (const timeZone of ['Europe/Paris', 'America/Martinique']) {
  describe(`agendaEntryCreatedPreview in ${timeZone}`, () => {
    const original = process.env.TZ
    before(() => {
      process.env.TZ = timeZone
    })
    after(() => {
      if (original === undefined) {
        delete process.env.TZ
      } else {
        process.env.TZ = original
      }
    })

    it('says a series repeats, from its first date to its last, the year once', () => {
      assert.equal(
        agendaEntryCreatedPreview(payload({})),
        "a ajouté l'événement hebdomadaire « Répétition » du 6 janvier au 30 juin 2027"
      )
    })

    it('names every frequency', () => {
      const sentences = ['daily', 'monthly', 'yearly'].map((frequency) =>
        agendaEntryCreatedPreview(payload({ recurrence_frequency: frequency }))
      )

      assert.deepEqual(sentences, [
        "a ajouté l'événement quotidien « Répétition » du 6 janvier au 30 juin 2027",
        "a ajouté l'événement mensuel « Répétition » du 6 janvier au 30 juin 2027",
        "a ajouté l'événement annuel « Répétition » du 6 janvier au 30 juin 2027"
      ])
    })

    it('starts an all day series on the day it was written for', () => {
      const allDay = payload({ event_datetime: '2027-01-06T00:00:00+00:00', is_all_day: true })

      assert.equal(
        agendaEntryCreatedPreview(allDay),
        "a ajouté l'événement hebdomadaire « Répétition » du 6 janvier au 30 juin 2027"
      )
    })

    it('gives both years when the series runs into the next one', () => {
      const overNewYear = payload({
        event_datetime: '2026-12-15T19:00:00+01:00',
        recurrence_until_date: '2027-03-31'
      })

      assert.equal(
        agendaEntryCreatedPreview(overNewYear),
        "a ajouté l'événement hebdomadaire « Répétition » du 15 décembre 2026 au 31 mars 2027"
      )
    })

    it('reads as a single date without a frequency it knows or a last day', () => {
      const single = "a ajouté l'événement « Répétition » le 6 janvier 2027"
      const stored = payload({})
      delete stored.recurrence_frequency
      delete stored.recurrence_until_date

      assert.deepEqual(
        [
          stored,
          payload({ recurrence_frequency: null, recurrence_until_date: null }),
          payload({ recurrence_frequency: 'fortnightly' }),
          payload({ recurrence_frequency: 'constructor' }),
          payload({ recurrence_until_date: null }),
          payload({ recurrence_until_date: 'someday' })
        ].map(agendaEntryCreatedPreview),
        Array(6).fill(single)
      )
    })
  })
}

describe('agendaEntryCreatedPreview in English', () => {
  const original = process.env.TZ
  before(() => {
    process.env.TZ = 'Europe/London'
    i18n.global.setLocaleMessage('en', en)
    i18n.global.locale.value = 'en'
  })
  after(() => {
    i18n.global.locale.value = 'fr'
    if (original === undefined) {
      delete process.env.TZ
    } else {
      process.env.TZ = original
    }
  })

  it('puts the frequency before the noun', () => {
    assert.equal(
      agendaEntryCreatedPreview(payload({})),
      'added the weekly event "Répétition" from 6 January to 30 June 2027'
    )
  })

  it('keeps the single date sentence for an entry that does not repeat', () => {
    assert.equal(
      agendaEntryCreatedPreview(
        payload({ recurrence_frequency: null, recurrence_until_date: null })
      ),
      'added the event "Répétition" for 6 January 2027'
    )
  })
})
