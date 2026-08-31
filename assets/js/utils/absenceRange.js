import { format } from 'date-fns'
import { fr } from 'date-fns/locale'
import { toAgendaDate } from './agendaDate.js'

/**
 * How a member absence reads in the management drawer: its date range as one label, and the list
 * grouped by the month it starts in.
 *
 * Both take the API shape, so bare `yyyy-MM-dd` strings. An absence is a range of calendar dates and
 * never an instant, so the dates are read through `toAgendaDate`, the one helper that knows how an
 * agenda date has to be unpinned. Reading them any other way is the bug class agendaDate.js exists
 * to keep closed, and the day `start_date` ever grows a time, this module follows it rather than
 * disagreeing with the calendar by a day west of UTC.
 *
 * Pure date arithmetic, so it is unit tested without a browser (npm test).
 */

/** A `yyyy-MM-dd` string as a local Date, or null when it is unusable. */
function toDate(dateString) {
  return toAgendaDate(dateString, true)
}

/**
 * The range as one line: « 22 août » for a single day, « 10 → 12 août » when both ends share a
 * month, « 28 août → 2 septembre » otherwise. The year is added only when the range crosses one, so
 * the common case stays short.
 */
export function formatAbsenceRange(startDate, endDate) {
  const start = toDate(startDate)
  const end = toDate(endDate)
  if (start === null || end === null) return ''

  if (start.getTime() === end.getTime()) {
    return format(start, 'd MMMM', { locale: fr })
  }

  const sameYear = start.getFullYear() === end.getFullYear()
  const sameMonth = sameYear && start.getMonth() === end.getMonth()

  if (sameMonth) {
    return `${format(start, 'd', { locale: fr })} → ${format(end, 'd MMMM', { locale: fr })}`
  }

  const pattern = sameYear ? 'd MMMM' : 'd MMMM yyyy'

  return `${format(start, pattern, { locale: fr })} → ${format(end, pattern, { locale: fr })}`
}

/**
 * The absences grouped by the month they start in, months in chronological order and each group's
 * rows sorted by start date then end date.
 *
 * Grouped by the start alone, so an absence spanning two months appears once. Repeating it under
 * every month it touches would double count a fortnight straddling the 1st, and the list is a
 * register of what was declared, not a calendar.
 *
 * Returns `[{ key: 'yyyy-MM', label: 'Août 2026', absences: [...] }]`.
 */
export function groupAbsencesByMonth(absences) {
  const groups = new Map()

  for (const absence of absences ?? []) {
    const start = toDate(absence?.start_date)
    if (start === null) continue

    // The key is the row's own month prefix; formatting the parsed Date would give the same string
    // at the cost of a locale-aware format call per row.
    const key = absence.start_date.slice(0, 7)
    if (!groups.has(key)) {
      const label = format(start, 'LLLL yyyy', { locale: fr })
      groups.set(key, { key, label: label.charAt(0).toUpperCase() + label.slice(1), absences: [] })
    }
    groups.get(key).absences.push(absence)
  }

  // Sorted in place: these groups were built here and belong to nobody else, and the spread that
  // used to wrap them read as a copy while sort mutated the array underneath it anyway.
  for (const group of groups.values()) {
    group.absences.sort(
      (a, b) => a.start_date.localeCompare(b.start_date) || a.end_date.localeCompare(b.end_date)
    )
  }

  return Array.from(groups.values()).sort((a, b) => a.key.localeCompare(b.key))
}
