import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { isAllDayItem } from './agendaItem.js'

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
