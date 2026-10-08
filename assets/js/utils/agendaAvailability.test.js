import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  availabilityAnswerLabel,
  availabilitySummary,
  canAnswerFromAgenda,
  isOccurrencePast,
  withAvailabilityAnswer
} from './agendaAvailability.js'

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

// 23:30 in Paris on 14 October, still the 14th in UTC.
const NOW = new Date('2026-10-14T21:30:00Z')

function manualItem(metadata = {}) {
  return {
    id: 'manual-e1',
    source: 'manual',
    source_id: 'e1',
    metadata: { ask_availability: true, occurrence_date: '2026-10-15', ...metadata }
  }
}

describe('isOccurrencePast', () => {
  it('compares UTC days, as the server does', () => {
    assert.equal(isOccurrencePast('2026-10-13', NOW), true)
    assert.equal(isOccurrencePast('2026-10-14', NOW), false)
    assert.equal(isOccurrencePast('2026-10-15', NOW), false)
  })
})

describe('canAnswerFromAgenda', () => {
  it('offers the buttons on an upcoming entry that asks', () => {
    assert.equal(canAnswerFromAgenda(manualItem(), NOW), true)
  })

  it('hides them on an entry that does not ask', () => {
    assert.equal(canAnswerFromAgenda(manualItem({ ask_availability: false }), NOW), false)
  })

  it('hides them once the date is past', () => {
    assert.equal(canAnswerFromAgenda(manualItem({ occurrence_date: '2026-10-01' }), NOW), false)
  })

  it('hides them on anything that is not a manual entry', () => {
    assert.equal(canAnswerFromAgenda({ ...manualItem(), source: 'task' }, NOW), false)
  })
})

describe('withAvailabilityAnswer', () => {
  const answer = { totals: { yes: 1, no: 0, absent: 0, pending: 2 }, my_answer: 'yes' }

  it('updates only the occurrence that was answered', () => {
    const answered = manualItem()
    const otherDate = { ...manualItem({ occurrence_date: '2026-10-22' }), id: 'manual-e1-22' }
    const task = { id: 'task-1', source: 'task', source_id: 'e1', metadata: {} }

    const [updated, untouchedDate, untouchedTask] = withAvailabilityAnswer(
      [answered, otherDate, task],
      'e1',
      '2026-10-15',
      answer
    )

    assert.deepEqual(updated.metadata, {
      ask_availability: true,
      occurrence_date: '2026-10-15',
      availability: answer.totals,
      my_availability: 'yes'
    })
    assert.equal(untouchedDate, otherDate)
    assert.equal(untouchedTask, task)
  })

  it('leaves the original item untouched', () => {
    const original = manualItem()
    withAvailabilityAnswer([original], 'e1', '2026-10-15', answer)

    assert.equal(original.metadata.my_availability, undefined)
  })
})
