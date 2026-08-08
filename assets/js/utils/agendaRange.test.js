import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { agendaViewForSavedEntry, isEntryVisibleInRange } from './agendaRange.js'

/**
 * The rule that keeps a just saved event on screen. Datetimes are written without a UTC offset on
 * purpose: parseISO then reads them as local time, so the assertions hold in whatever timezone the
 * runner uses.
 *
 * Run with `npm test`.
 */

const SEPTEMBER_FROM = new Date(2026, 8, 1)
const SEPTEMBER_TO = new Date(2026, 8, 30)

/** An agenda entry as the API returns it, with every optional date explicitly absent. */
function entry(fields) {
  return { event_datetime: null, end_datetime: null, recurrence_until_date: null, ...fields }
}

describe('isEntryVisibleInRange', () => {
  it('sees an entry starting inside the period', () => {
    const saved = entry({ event_datetime: '2026-09-20T18:00:00' })

    assert.equal(isEntryVisibleInRange(saved, SEPTEMBER_FROM, SEPTEMBER_TO), true)
  })

  // The period bounds are day granular, so an evening event on the last day is still inside it.
  // A plain date comparison against a start of day bound would drop it.
  it('sees an evening event on the last day of the period', () => {
    const saved = entry({ event_datetime: '2026-09-30T23:30:00' })

    assert.equal(isEntryVisibleInRange(saved, SEPTEMBER_FROM, SEPTEMBER_TO), true)
  })

  it('sees an event at the very start of the first day', () => {
    const saved = entry({ event_datetime: '2026-09-01T00:00:00' })

    assert.equal(isEntryVisibleInRange(saved, SEPTEMBER_FROM, SEPTEMBER_TO), true)
  })

  it('does not see the days just outside the period', () => {
    const before = entry({ event_datetime: '2026-08-31T23:00:00' })
    const after = entry({ event_datetime: '2026-10-01T08:00:00' })

    assert.equal(isEntryVisibleInRange(before, SEPTEMBER_FROM, SEPTEMBER_TO), false)
    assert.equal(isEntryVisibleInRange(after, SEPTEMBER_FROM, SEPTEMBER_TO), false)
  })

  it('sees a series that started before the period but still runs through it', () => {
    const saved = entry({
      event_datetime: '2026-06-01T20:00:00',
      recurrence_until_date: '2026-12-31'
    })

    assert.equal(isEntryVisibleInRange(saved, SEPTEMBER_FROM, SEPTEMBER_TO), true)
  })

  it('does not see a series that ended before the period', () => {
    const saved = entry({
      event_datetime: '2026-01-05T20:00:00',
      recurrence_until_date: '2026-03-31'
    })

    assert.equal(isEntryVisibleInRange(saved, SEPTEMBER_FROM, SEPTEMBER_TO), false)
  })

  it('sees a multi day event that only overlaps the start of the period', () => {
    const saved = entry({
      event_datetime: '2026-08-30T10:00:00',
      end_datetime: '2026-09-02T18:00:00'
    })

    assert.equal(isEntryVisibleInRange(saved, SEPTEMBER_FROM, SEPTEMBER_TO), true)
  })

  it('sees nothing when the entry or the period has no usable date', () => {
    const saved = entry({ event_datetime: '2026-09-20T18:00:00' })

    assert.equal(isEntryVisibleInRange(entry({}), SEPTEMBER_FROM, SEPTEMBER_TO), false)
    assert.equal(isEntryVisibleInRange(null, SEPTEMBER_FROM, SEPTEMBER_TO), false)
    assert.equal(isEntryVisibleInRange(saved, null, SEPTEMBER_TO), false)
    assert.equal(isEntryVisibleInRange(saved, SEPTEMBER_FROM, undefined), false)
  })
})

describe('agendaViewForSavedEntry', () => {
  it('keeps the period when the entry is already on screen', () => {
    const saved = entry({ event_datetime: '2026-09-20T18:00:00' })

    assert.equal(agendaViewForSavedEntry(saved, SEPTEMBER_FROM, SEPTEMBER_TO), null)
  })

  it('moves to the month of an entry saved after the period', () => {
    const saved = entry({ event_datetime: '2026-10-20T18:30:00' })

    assert.deepEqual(agendaViewForSavedEntry(saved, SEPTEMBER_FROM, SEPTEMBER_TO), {
      from: new Date(2026, 9, 1),
      to: new Date(2026, 9, 31, 23, 59, 59, 999),
      // The entry's own day, so the day and week views open on it and not on the 1st.
      focusDate: new Date(2026, 9, 20, 18, 30)
    })
  })

  it('moves to the month of an entry saved before the period', () => {
    const saved = entry({ event_datetime: '2026-07-04T09:00:00' })

    assert.deepEqual(agendaViewForSavedEntry(saved, SEPTEMBER_FROM, SEPTEMBER_TO), {
      from: new Date(2026, 6, 1),
      to: new Date(2026, 6, 31, 23, 59, 59, 999),
      focusDate: new Date(2026, 6, 4, 9, 0)
    })
  })

  it('stays put when the saved response carries no date', () => {
    assert.equal(agendaViewForSavedEntry(entry({}), SEPTEMBER_FROM, SEPTEMBER_TO), null)
    assert.equal(agendaViewForSavedEntry(undefined, SEPTEMBER_FROM, SEPTEMBER_TO), null)
  })
})

describe('entries that end after their first occurrence', () => {
  it('keeps a recurring multi day series visible through its last occurrence', () => {
    // Monday 20:00 to Wednesday 22:00, weekly until 30 November. The last occurrence still runs two
    // days past that horizon, so a period opening on 1 December does contain it. Reading the
    // horizon as the end would call it gone and jump the user away from a series they can see.
    const series = entry({
      event_datetime: '2026-09-07T20:00:00',
      end_datetime: '2026-09-09T22:00:00',
      recurrence_until_date: '2026-11-30'
    })

    assert.equal(isEntryVisibleInRange(series, new Date(2026, 11, 1), new Date(2026, 11, 31)), true)
    assert.equal(
      isEntryVisibleInRange(series, new Date(2026, 11, 3), new Date(2026, 11, 31)),
      false
    )
  })

  it('keeps a plain multi day event visible at the end of its span', () => {
    const festival = entry({
      event_datetime: '2026-09-28T10:00:00',
      end_datetime: '2026-10-02T23:00:00'
    })

    assert.equal(isEntryVisibleInRange(festival, new Date(2026, 9, 1), new Date(2026, 9, 31)), true)
  })
})

describe('all day entries, which the API pins to UTC midnight', () => {
  // The one case that has to carry a real offset: the API normalises an all day entry to UTC
  // midnight, so west of UTC an instant reading lands on the day before. Guadeloupe and Martinique
  // are UTC-4 all year, so the wrong reading shows a French user the previous day.
  const ALL_DAY_FIRST_OF_SEPTEMBER = entry({
    event_datetime: '2026-09-01T00:00:00+00:00',
    is_all_day: true
  })

  it('reads the day as written rather than shifting it west of UTC', () => {
    assert.equal(
      isEntryVisibleInRange(ALL_DAY_FIRST_OF_SEPTEMBER, SEPTEMBER_FROM, SEPTEMBER_TO),
      true
    )
  })

  it('does not jump away from a period that already shows the entry', () => {
    assert.equal(
      agendaViewForSavedEntry(ALL_DAY_FIRST_OF_SEPTEMBER, SEPTEMBER_FROM, SEPTEMBER_TO),
      null
    )
  })
})
