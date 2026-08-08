import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  agendaSeriesSubmission,
  SERIES_IMPACT_NONE,
  SERIES_IMPACT_RECURRENCE_REMOVED,
  SERIES_IMPACT_SHIFT
} from './agendaSeriesEdit.js'

/**
 * The rule that keeps a recurring series intact when one of its occurrences is edited. Dates are
 * built with local constructors so the assertions hold in whatever timezone the runner uses.
 *
 * Run with `npm test`.
 */

// Weekly rehearsal: anchored Monday 5 January 2026 at 20:00, opened on the 9 March occurrence.
const ANCHOR_START = new Date(2026, 0, 5, 20, 0)
const OCCURRENCE_START = new Date(2026, 2, 9, 20, 0)

function series(overrides) {
  return agendaSeriesSubmission({
    anchorStart: ANCHOR_START,
    occurrenceStart: OCCURRENCE_START,
    formStart: OCCURRENCE_START,
    formEnd: null,
    isAllDay: false,
    keepsRecurrence: true,
    ...overrides
  })
}

describe('editing an occurrence without touching its date', () => {
  // The bug this whole module exists for: sending the occurrence back as the series start moved the
  // anchor from 5 January to 9 March and dropped the nine occurrences before it.
  it('leaves the start out of the request so the anchor survives a typo fix', () => {
    const submission = series({})

    assert.equal(submission.start, undefined)
    assert.equal(submission.impact, SERIES_IMPACT_NONE)
  })

  it('leaves the start out even when the form is a distinct Date with the same instant', () => {
    const submission = series({ formStart: new Date(OCCURRENCE_START.getTime()) })

    assert.equal(submission.start, undefined)
  })

  it('restretches every occurrence when only the end time is edited', () => {
    // 20:00 to 22:00 on screen, so the series must run 20:00 to 22:00 from its own anchor.
    const submission = series({ formEnd: new Date(2026, 2, 9, 22, 0) })

    assert.equal(submission.start, undefined)
    assert.deepEqual(submission.end, new Date(2026, 0, 5, 22, 0))
    assert.equal(submission.impact, SERIES_IMPACT_NONE)
  })

  it('clears the end when the form has none', () => {
    const submission = series({ formEnd: null })

    assert.equal(submission.end, null)
  })
})

describe('moving the occurrence moves the whole series by the same amount', () => {
  it('turns a Monday series into a Tuesday series', () => {
    const submission = series({ formStart: new Date(2026, 2, 10, 20, 0) })

    assert.deepEqual(submission.start, new Date(2026, 0, 6, 20, 0))
    assert.equal(submission.impact, SERIES_IMPACT_SHIFT)
  })

  it('applies a time-only change to the anchor', () => {
    const submission = series({ formStart: new Date(2026, 2, 9, 21, 30) })

    assert.deepEqual(submission.start, new Date(2026, 0, 5, 21, 30))
    assert.equal(submission.impact, SERIES_IMPACT_SHIFT)
  })

  it('moves the series backwards too', () => {
    const submission = series({ formStart: new Date(2026, 2, 8, 20, 0) })

    assert.deepEqual(submission.start, new Date(2026, 0, 4, 20, 0))
  })

  it('carries the end along with the moved start, duration intact', () => {
    const submission = series({
      formStart: new Date(2026, 2, 10, 20, 0),
      formEnd: new Date(2026, 2, 10, 22, 0)
    })

    assert.deepEqual(submission.start, new Date(2026, 0, 6, 20, 0))
    assert.deepEqual(submission.end, new Date(2026, 0, 6, 22, 0))
  })

  it('takes the new duration when the move and the end are edited together', () => {
    const submission = series({
      formStart: new Date(2026, 2, 10, 20, 0),
      formEnd: new Date(2026, 2, 10, 23, 30)
    })

    assert.deepEqual(submission.end, new Date(2026, 0, 6, 23, 30))
  })
})

describe('all-day series, which only the calendar day counts for', () => {
  // Stored at UTC midnight, so the parsed occurrence sits at an offset that differs from the
  // anchor's whenever the two fall in different daylight saving periods. A millisecond delta then
  // reads as 22 or 23 hours and lands the anchor a day short; counting calendar days does not.
  const ALL_DAY_ANCHOR = new Date(2026, 0, 5, 1, 0) // January, UTC+1 in Paris
  const ALL_DAY_OCCURRENCE = new Date(2026, 6, 6, 2, 0) // July, UTC+2 in Paris

  it('moves the anchor exactly one day when the occurrence moves one day', () => {
    const submission = agendaSeriesSubmission({
      anchorStart: ALL_DAY_ANCHOR,
      occurrenceStart: ALL_DAY_OCCURRENCE,
      // A date picker that resets the time to local midnight would make this 22 hours, not 24.
      formStart: new Date(2026, 6, 7, 0, 0),
      formEnd: null,
      isAllDay: true,
      keepsRecurrence: true
    })

    assert.equal(submission.impact, SERIES_IMPACT_SHIFT)
    assert.equal(submission.start.getFullYear(), 2026)
    assert.equal(submission.start.getMonth(), 0)
    assert.equal(submission.start.getDate(), 6)
  })

  it('sees no move when only the time part differs from the stored occurrence', () => {
    const submission = agendaSeriesSubmission({
      anchorStart: ALL_DAY_ANCHOR,
      occurrenceStart: ALL_DAY_OCCURRENCE,
      formStart: new Date(2026, 6, 6, 0, 0),
      formEnd: null,
      isAllDay: true,
      keepsRecurrence: true
    })

    assert.equal(submission.start, undefined)
    assert.equal(submission.impact, SERIES_IMPACT_NONE)
  })

  it('keeps a multi-day span in days rather than in hours', () => {
    const submission = agendaSeriesSubmission({
      anchorStart: ALL_DAY_ANCHOR,
      occurrenceStart: ALL_DAY_OCCURRENCE,
      formStart: new Date(2026, 6, 6, 0, 0),
      formEnd: new Date(2026, 6, 8, 0, 0),
      isAllDay: true,
      keepsRecurrence: true
    })

    assert.equal(submission.start, undefined)
    assert.equal(submission.end.getMonth(), 0)
    assert.equal(submission.end.getDate(), 7)
  })
})

describe('turning the repetition off', () => {
  it('keeps the single remaining event on the date the drawer is showing', () => {
    const submission = series({ keepsRecurrence: false })

    assert.deepEqual(submission.start, OCCURRENCE_START)
    assert.equal(submission.impact, SERIES_IMPACT_RECURRENCE_REMOVED)
  })

  it('keeps an edited date rather than the occurrence it replaced', () => {
    const formStart = new Date(2026, 2, 12, 18, 0)
    const submission = series({ formStart, keepsRecurrence: false })

    assert.deepEqual(submission.start, formStart)
    assert.equal(submission.impact, SERIES_IMPACT_RECURRENCE_REMOVED)
  })
})

describe('entries that are not an occurrence of a series', () => {
  it('submits the form as typed for a one-off entry', () => {
    const formStart = new Date(2026, 2, 9, 20, 0)
    const submission = agendaSeriesSubmission({
      anchorStart: null,
      occurrenceStart: null,
      formStart,
      formEnd: null,
      keepsRecurrence: false
    })

    assert.deepEqual(submission.start, formStart)
    assert.equal(submission.end, null)
    assert.equal(submission.impact, SERIES_IMPACT_NONE)
  })

  it('submits the form as typed when a one-off entry becomes a series', () => {
    const formStart = new Date(2026, 2, 9, 20, 0)
    const submission = agendaSeriesSubmission({
      anchorStart: null,
      occurrenceStart: null,
      formStart,
      formEnd: null,
      keepsRecurrence: true
    })

    assert.deepEqual(submission.start, formStart)
    assert.equal(submission.impact, SERIES_IMPACT_NONE)
  })

  it('sends a null start rather than omitting it when the form has no date', () => {
    // Omitting it would turn a missing required field into a silent no-op instead of the
    // validation error the form relies on.
    const submission = agendaSeriesSubmission({
      anchorStart: null,
      occurrenceStart: null,
      formStart: null,
      formEnd: null,
      keepsRecurrence: false
    })

    assert.equal(submission.start, null)
    assert.equal(submission.impact, SERIES_IMPACT_NONE)
  })

  it('submits as typed when the series metadata is missing or unusable', () => {
    const formStart = new Date(2026, 2, 9, 20, 0)

    assert.deepEqual(
      agendaSeriesSubmission({
        anchorStart: new Date('nonsense'),
        occurrenceStart: OCCURRENCE_START,
        formStart,
        formEnd: null,
        keepsRecurrence: true
      }).start,
      formStart
    )
    assert.deepEqual(
      agendaSeriesSubmission({
        anchorStart: ANCHOR_START,
        occurrenceStart: undefined,
        formStart,
        formEnd: null,
        keepsRecurrence: true
      }).start,
      formStart
    )
  })
})
