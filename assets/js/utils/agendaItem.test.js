import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { agendaItemLink, isAllDayItem } from './agendaItem.js'

/**
 * The shared all day rule for agenda items. Datetimes are written the way the API sends them,
 * pinned to UTC, because that offset is exactly what used to leak into the dashboard as a 02:00
 * that nobody had typed.
 *
 * Run with `npm test`.
 */

/** An agenda item as the aggregator returns it. */
function item(fields) {
  return { source: 'manual', is_all_day: false, datetime: '2026-08-12T20:30:00+02:00', ...fields }
}

describe('isAllDayItem', () => {
  it('keeps the time of a manual event the user gave an hour to', () => {
    assert.equal(isAllDayItem(item({})), false)
  })

  it('drops the time of a manual all day event', () => {
    const festivalDay = item({ is_all_day: true, datetime: '2026-08-12T00:00:00+00:00' })

    assert.equal(isAllDayItem(festivalDay), true)
  })

  // The aggregator sends is_all_day false on finance items and pads their date only column to
  // midnight, so trusting that flag alone shows a due date as 02:00.
  it('drops the time of a finance due date despite its is_all_day being false', () => {
    const dueDate = item({ source: 'finance', datetime: '2026-08-12T00:00:00+00:00' })

    assert.equal(isAllDayItem(dueDate), true)
  })

  it('drops the time of a task due date despite its is_all_day being false', () => {
    const dueDate = item({ source: 'task', datetime: '2026-08-12T00:00:00+00:00' })

    assert.equal(isAllDayItem(dueDate), true)
  })

  it('drops the time of a source it does not know rather than trusting the padding', () => {
    assert.equal(isAllDayItem(item({ source: 'gig' })), true)
  })

  it('reads a missing item as all day instead of throwing', () => {
    assert.equal(isAllDayItem(null), true)
    assert.equal(isAllDayItem(undefined), true)
  })
})

describe('agendaItemLink', () => {
  const params = { id: 'space-1' }

  it('opens a band entry on its occurrence, or on the entry alone', () => {
    const occurrence = {
      source: 'manual',
      source_id: 'e1',
      metadata: { occurrence_date: '2026-10-15' }
    }
    assert.deepEqual(agendaItemLink(occurrence, 'space-1'), {
      name: 'app_band_agenda',
      params,
      query: { entry: 'e1', occurrence: '2026-10-15' }
    })
    assert.deepEqual(agendaItemLink({ source: 'manual', source_id: 'e1' }, 'space-1'), {
      name: 'app_band_agenda',
      params,
      query: { entry: 'e1' }
    })
  })

  it('opens the task or the finance entry an item comes from', () => {
    assert.deepEqual(agendaItemLink({ source: 'task', source_id: 't1' }, 'space-1'), {
      name: 'app_band_tasks',
      params,
      query: { task: 't1' }
    })
    assert.deepEqual(agendaItemLink({ source: 'finance', source_id: 'f1' }, 'space-1'), {
      name: 'app_band_finance',
      params,
      query: { entry: 'f1' }
    })
  })

  it('leads nowhere for an absence or an item without a source', () => {
    assert.equal(agendaItemLink({ source: 'absence', source_id: 'a1' }, 'space-1'), null)
    assert.equal(agendaItemLink({ source: 'task' }, 'space-1'), null)
  })
})
