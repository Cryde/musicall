import { addDays, format, parseISO } from 'date-fns'
import { isAllDayItem } from './agendaItem.js'

/**
 * How an agenda datetime has to be read so it lands on the day it was written for.
 *
 * An all day entry carries no instant: the API pins it to midnight UTC, and finance and task due
 * dates are date only columns the aggregator pads the same way. Reading any of those back as an
 * instant and then applying local getters lands on the previous day for anyone west of UTC, and
 * Guadeloupe and Martinique are UTC-4 all year, so that is a real French user rather than a
 * hypothetical one. The pin is dropped instead: a bare `yyyy-MM-dd` string has no offset to
 * convert, so it holds under any timezone.
 *
 * A timed event is the opposite case. It is a real instant and has to keep it, so it shows at the
 * hour and on the local day the reader actually lives it. Nothing here may unpin one.
 *
 * This lives outside the components because the agenda grid, the agenda list, the year overview
 * dots, the save toast, the dashboard widget, the entry drawer and the notification feed all have
 * to answer it the same way, and they did not: this bug class has come back three times. It is
 * pure date arithmetic, so it is unit tested without a browser and in several timezones (npm test).
 */

/**
 * The datetime to hand a formatter: the date portion for something that covers a day, the instant
 * untouched for something that happens at a moment.
 */
export function withoutAllDayPin(value, isAllDay) {
  return isAllDay && typeof value === 'string' ? value.slice(0, 10) : value
}

/**
 * The Date an agenda datetime points at: local midnight of the written day when it covers a day,
 * the instant itself when it happens at a moment. Anything unusable reads as null, so callers fall
 * back instead of throwing.
 */
export function toAgendaDate(value, isAllDay) {
  if (!value) return null
  const unpinned = withoutAllDayPin(value, isAllDay)
  const date = typeof unpinned === 'string' ? parseISO(unpinned) : unpinned
  if (!(date instanceof Date) || Number.isNaN(date.getTime())) return null

  return date
}

/** The `yyyy-MM-dd` day an agenda datetime belongs to, or null when there is no usable date. */
function agendaDayKey(value, isAllDay) {
  const date = toAgendaDate(value, isAllDay)

  return date === null ? null : format(date, 'yyyy-MM-dd')
}

/**
 * The days an agenda item occupies, as `yyyy-MM-dd` keys, for the list grouping and the year
 * overview dots. Takes the API shape of an agenda item, so snake_case properties.
 *
 * Only an all day event spans several days. A timed multi day event would repeat its start day
 * time range on every intermediate day, which reads as several separate events.
 *
 * Whether the item covers a day is the derived rule, the same one agendaCalendarEventDates asks,
 * and not the raw `is_all_day` flag. Reading the flag here would agree with the calendar only for
 * as long as the aggregated sources keep sending a null end alongside their false flag: the day a
 * task or a finance due date grows a real end, the list and the grid would disagree about how many
 * days it covers, and nothing would say so.
 */
export function agendaItemDayKeys(item) {
  const isAllDay = isAllDayItem(item)
  const startKey = agendaDayKey(item?.datetime, isAllDay)
  if (startKey === null) return []
  if (!isAllDay || !item.end_datetime) return [startKey]

  const endKey = agendaDayKey(item.end_datetime, isAllDay)
  if (endKey === null || endKey <= startKey) return [startKey]

  const keys = []
  let cursor = parseISO(startKey)
  const end = parseISO(endKey)
  while (cursor <= end) {
    keys.push(format(cursor, 'yyyy-MM-dd'))
    cursor = addDays(cursor, 1)
  }

  return keys
}

/**
 * The `start` and `end` an agenda item is fed to FullCalendar with.
 *
 * The calendar's timezone is left at its default `local`, and for that setting DateEnv turns a
 * start carrying an offset into local wall clock and re-tags it as UTC before the day cell is
 * picked. An all day event pinned to midnight UTC is therefore drawn in the previous day's cell
 * west of UTC, on the month, week, day and year views alike, which is a wrong grid and not just a
 * wrong label. A bare date has no offset to convert, so it is taken as written. A timed event keeps
 * its instant, because that conversion is exactly what puts it at the right hour.
 */
export function agendaCalendarEventDates(item) {
  if (!isAllDayItem(item)) {
    return { start: item?.datetime, end: item?.end_datetime ?? undefined }
  }

  const lastDay = item?.end_datetime ? agendaDayKey(item.end_datetime, true) : null

  return {
    start: agendaDayKey(item?.datetime, true),
    // FullCalendar reads an all day end as exclusive, so the last day is bumped to the day after.
    end: lastDay === null ? undefined : format(addDays(parseISO(lastDay), 1), 'yyyy-MM-dd')
  }
}
