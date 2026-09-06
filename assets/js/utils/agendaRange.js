import { addDays, endOfMonth, format, startOfMonth } from 'date-fns'
import { toAgendaDate } from './agendaDate.js'

/**
 * Which period of the band space agenda is on screen, and which one has to be.
 *
 * This lives outside Agenda.vue because it is the rule that decides whether a save is visible:
 * get it wrong and the agenda refreshes into a period that does not contain the entry, which
 * reads as a failed save and gets the user to save again. It is pure date arithmetic, so it is
 * unit tested without a browser (npm test).
 *
 * It is also where a period gets turned into the pair of bounds the API takes, so that rule has one
 * home rather than a copy in every caller. That is what #944 was: the dashboard widget kept its own
 * copy, #935 changed the rule, and only the copy in Agenda.vue was updated.
 */

/** Local calendar day: the period is day granular, its bounds are 00:00:00 and 23:59:59. */
function dayKey(date) {
  return format(date, 'yyyy-MM-dd')
}

/**
 * The days an entry occupies: its start, stretched to the last day of a multi day event or of a
 * recurring series. Takes the API shape of an agenda entry, so snake_case properties.
 */
function entrySpan(entry) {
  const isAllDay = entry?.is_all_day === true
  const start = toAgendaDate(entry?.event_datetime, isAllDay)
  if (!start) return null

  const ownEnd = toAgendaDate(entry?.end_datetime, isAllDay) ?? start

  // Every occurrence keeps the duration of the first one, so a series runs past its horizon by
  // however long a single occurrence lasts. Taking the horizon as the end would cut that tail off
  // and report a Monday to Wednesday weekly event as gone two days before it actually is.
  // The horizon is a date only field already, so it is read as written whatever the entry is.
  const horizon = toAgendaDate(entry?.recurrence_until_date, false)
  const lastEnd = horizon
    ? new Date(horizon.getTime() + (ownEnd.getTime() - start.getTime()))
    : ownEnd
  const end = lastEnd > ownEnd ? lastEnd : ownEnd

  return { start, end: end < start ? start : end }
}

/**
 * Whether any day of the entry is on screen. A weekly series that started in June is visible in
 * September, so the whole span counts and not only the first occurrence.
 */
export function isEntryVisibleInRange(entry, from, to) {
  const span = entrySpan(entry)
  const rangeStart = toAgendaDate(from, false)
  const rangeEnd = toAgendaDate(to, false)
  if (!span || !rangeStart || !rangeEnd) return false

  return dayKey(span.start) <= dayKey(rangeEnd) && dayKey(span.end) >= dayKey(rangeStart)
}

/**
 * Where the agenda must point after a save, or null to stay put because the entry is already on
 * screen. `focusDate` is the entry's own day rather than the month start, so the day and week
 * views open on the entry instead of on the first of the month.
 */
export function agendaViewForSavedEntry(entry, from, to) {
  const span = entrySpan(entry)
  if (!span) return null
  if (isEntryVisibleInRange(entry, from, to)) return null

  return { from: startOfMonth(span.start), to: endOfMonth(span.start), focusDate: span.start }
}

/**
 * The bounds the dashboard's « Agenda à venir » widget asks for: today, and the next `days` days.
 *
 * Bare calendar days, never a wall clock. The API's `from` and `to` are `Assert\Date`, an anchored
 * `^\d{4}-\d{2}-\d{2}$`, so a `2026-09-06T00:00:00` is a 422 rather than a narrower window. It
 * also widens `to` to the end of its day itself, so appending `T23:59:59` here would put that rule
 * in two places for no gain (#935).
 */
export function upcomingAgendaWindow(today, days) {
  return { from: dayKey(today), to: dayKey(addDays(today, days)) }
}
