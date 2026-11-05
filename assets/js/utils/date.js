import { format, parseISO } from 'date-fns'
import { dateFnsLocale, intlLocale, rangeArrow } from '../i18n/formatLocale.js'

export function formatDate(dateString) {
  return format(parseISO(dateString), 'dd/MM/yyyy HH:mm')
}

export function formatDateShort(dateString) {
  return format(parseISO(dateString), 'dd/MM/yyyy')
}

// « 10 août », « 3 févr. », from an ISO string or a Date.
export function formatDateCompact(value) {
  if (!value) return ''
  const date = typeof value === 'string' ? parseISO(value) : value
  return date.toLocaleDateString(intlLocale(), { day: 'numeric', month: 'short' })
}

export function formatDateCompactWithYear(dateString) {
  if (!dateString) return ''
  const date = parseISO(dateString)
  return date.toLocaleDateString(intlLocale(), { day: 'numeric', month: 'short', year: 'numeric' })
}

// « mer. 4 nov. 2026 », from an ISO string or a Date: a day a member can place in their week.
export function formatDateWithWeekday(value) {
  if (!value) return ''
  const date = typeof value === 'string' ? parseISO(value) : value
  return date.toLocaleDateString(intlLocale(), {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric'
  })
}

// The two halves of a small calendar leaf, « sam. » over « 7 », from an ISO string. Empty without a day.
export function formatWeekdayShort(dateString) {
  if (!dateString) return ''
  return parseISO(dateString).toLocaleDateString(intlLocale(), { weekday: 'short' })
}

export function formatDayOfMonth(dateString) {
  if (!dateString) return ''
  return String(parseISO(dateString).getDate())
}

// « lun. 30 nov. », from an ISO string or a Date: the weekday a row in a list can place, no year.
export function formatDayWithWeekday(value) {
  if (!value) return ''
  const date = typeof value === 'string' ? parseISO(value) : value
  return date.toLocaleDateString(intlLocale(), { weekday: 'short', day: 'numeric', month: 'short' })
}

// A list row's day: « 4 nov. » this year, « 4 nov. 2025 » before, where there is no column to repeat
// the year in. `now` is the clock, for tests.
export function formatDateCompactInYear(value, now = new Date()) {
  if (!value) return ''
  const date = typeof value === 'string' ? parseISO(value) : value
  if (date.getFullYear() === now.getFullYear()) return formatDateCompact(date)
  return date.toLocaleDateString(intlLocale(), { day: 'numeric', month: 'short', year: 'numeric' })
}

// A period on one line, « 4 nov. → 4 déc. 2026 », the year once when both ends share it. Takes Dates.
export function formatDateRangeCompact(from, to) {
  if (!from || !to) return ''
  const withYear = { day: 'numeric', month: 'short', year: 'numeric' }
  const start =
    from.getFullYear() === to.getFullYear()
      ? from.toLocaleDateString(intlLocale(), { day: 'numeric', month: 'short' })
      : from.toLocaleDateString(intlLocale(), withYear)
  return `${start} ${rangeArrow()} ${to.toLocaleDateString(intlLocale(), withYear)}`
}

// Spelled-out date, for copy that has to read as a sentence: « supprimé le 29 août 2026 ».
export function formatDateLong(dateString) {
  if (!dateString) return ''
  return format(parseISO(dateString), 'd MMMM yyyy', { locale: dateFnsLocale() })
}
