import { addDays, differenceInCalendarDays } from 'date-fns'

/**
 * What a save from the agenda drawer must send when the drawer was opened on one occurrence of a
 * recurring series.
 *
 * The agenda expands a series client-side: every occurrence carries the same `source_id`, so a
 * PATCH from the 9 March occurrence writes onto the series itself. The drawer nevertheless shows
 * 9 March, and sending that back as the series start moves the anchor from 5 January to 9 March,
 * which deletes the January and February occurrences. Hence the two rules below.
 *
 * 1. The date field is the date of the occurrence on screen. Leaving it alone must leave the anchor
 *    alone, so the start is left out of the request entirely: a merge patch without the key keeps
 *    the stored value.
 * 2. Moving it moves the whole series by the same amount, because there is no per-occurrence
 *    override to write to (`AgendaEntryException` only records cancellations). Moving an occurrence
 *    one day later turns a Monday series into a Tuesday series.
 *
 * Dropping the recurrence is the exception to rule 1: there is no series left to anchor, so the one
 * remaining event keeps the date the drawer is showing.
 */

/** The save changes nothing beyond the fields it edits, so it needs no confirmation. */
export const SERIES_IMPACT_NONE = 'none'
/** Every occurrence moves, the past ones included. */
export const SERIES_IMPACT_SHIFT = 'shift'
/** The series collapses to a single event and the other occurrences go. */
export const SERIES_IMPACT_RECURRENCE_REMOVED = 'recurrence_removed'

function isDate(value) {
  return value instanceof Date && !Number.isNaN(value.getTime())
}

/**
 * An all-day entry is stored at UTC midnight and serialised back as a bare date, so only the
 * calendar day counts. Shifting it by a millisecond delta is off by an hour whenever the anchor and
 * the occurrence sit in different daylight saving periods, which lands the anchor on the wrong day.
 * Counting calendar days holds in every timezone.
 */
function shiftDatetime(base, from, to, isAllDay) {
  if (isAllDay) {
    return addDays(base, differenceInCalendarDays(to, from))
  }

  return new Date(base.getTime() + (to.getTime() - from.getTime()))
}

function hasMoved(from, to, isAllDay) {
  return isAllDay ? differenceInCalendarDays(to, from) !== 0 : to.getTime() !== from.getTime()
}

/**
 * Which start and end a save must carry, and what the save does to the series.
 *
 * `anchorStart` is the series' stored start (`metadata.series_start_datetime`) and
 * `occurrenceStart` the occurrence the drawer was opened on; both are absent for a one-off entry
 * and for a creation, which then submit the form as typed.
 *
 * A `start` of `undefined` means "leave the key out of the request". Any other value, `null`
 * included, is sent as is.
 *
 * @returns {{ start: Date|null|undefined, end: Date|null, impact: string }}
 */
export function agendaSeriesSubmission({
  anchorStart,
  occurrenceStart,
  formStart,
  formEnd,
  isAllDay = false,
  keepsRecurrence = false
}) {
  const asTyped = {
    start: isDate(formStart) ? formStart : null,
    end: isDate(formEnd) ? formEnd : null
  }
  const isSeriesOccurrence = isDate(anchorStart) && isDate(occurrenceStart)

  if (!isSeriesOccurrence || !isDate(formStart)) {
    return { ...asTyped, impact: SERIES_IMPACT_NONE }
  }

  // No series to anchor any more: the single event that remains keeps the date on screen, which is
  // the occurrence the user was looking at when they turned the repetition off.
  if (!keepsRecurrence) {
    return { ...asTyped, impact: SERIES_IMPACT_RECURRENCE_REMOVED }
  }

  const moved = hasMoved(occurrenceStart, formStart, isAllDay)
  const start = moved ? shiftDatetime(anchorStart, occurrenceStart, formStart, isAllDay) : undefined
  // The end follows the start and keeps whatever duration the form shows, so editing only the end
  // time restretches every occurrence without touching the anchor.
  const end = isDate(formEnd)
    ? shiftDatetime(start ?? anchorStart, formStart, formEnd, isAllDay)
    : null

  return { start, end, impact: moved ? SERIES_IMPACT_SHIFT : SERIES_IMPACT_NONE }
}
