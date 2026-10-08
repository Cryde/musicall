import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { availabilityAnswerLabel, availabilitySummary } from './agendaAvailability.js'

/**
 * The availability wording of an agenda entry. Run with `npm test`.
 */

describe('availabilitySummary', () => {
  it('lists every non zero count, plural where needed', () => {
    assert.equal(
      availabilitySummary({ yes: 3, no: 1, absent: 2, pending: 1 }),
      '3 dispos · 1 indispo · 2 absents · 1 sans réponse'
    )
  })

  it('leaves out the counts that are zero', () => {
    assert.equal(
      availabilitySummary({ yes: 1, no: 0, absent: 0, pending: 4 }),
      '1 dispo · 4 sans réponse'
    )
  })

  it('says nothing when there is nothing to count', () => {
    assert.equal(availabilitySummary({ yes: 0, no: 0, absent: 0, pending: 0 }), null)
  })

  // The iCal export and the non manual sources carry no totals.
  it('says nothing without totals', () => {
    assert.equal(availabilitySummary(null), null)
    assert.equal(availabilitySummary(undefined), null)
  })
})

describe('availabilityAnswerLabel', () => {
  it('names each state the API sends', () => {
    assert.equal(availabilityAnswerLabel('yes'), 'Disponible')
    assert.equal(availabilityAnswerLabel('no'), 'Indisponible')
    assert.equal(availabilityAnswerLabel('absent'), 'Absent')
  })

  it('reads a missing answer as no answer yet', () => {
    assert.equal(availabilityAnswerLabel(null), 'Sans réponse')
  })
})
