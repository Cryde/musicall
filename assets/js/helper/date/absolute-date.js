import { format, isThisYear, isToday, parseISO } from 'date-fns'
import { dateFnsLocale, datePattern } from '../../i18n/formatLocale.js'

/**
 * The exact moment a message was sent, for the hover label and the block header (#1031).
 *
 * Distinct from `relativeDate`, which answers « quand, à peu près » and is what the conversation
 * list wants. Here the point is precision, so the day is dropped only when it is today.
 */
export default function absoluteDate(value) {
  // `parseISO` throws on anything that is not a string, rather than returning an invalid date, so
  // the type is checked before the value. Its sibling in messageGrouping.js reads the same field
  // through `new Date()`, which never throws; this one has to guard for itself.
  if (typeof value !== 'string') {
    return ''
  }

  const parsed = parseISO(value)

  if (Number.isNaN(parsed.getTime())) {
    return ''
  }

  const options = { locale: dateFnsLocale() }

  if (isToday(parsed)) {
    return format(parsed, 'HH:mm', options)
  }

  if (isThisYear(parsed)) {
    return format(parsed, datePattern('dayMonthTime'), options)
  }

  return format(parsed, datePattern('dayMonthYearTime'), options)
}
