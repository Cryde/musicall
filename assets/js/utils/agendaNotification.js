import { format, isValid, parseISO } from 'date-fns'
import { dateFnsLocale } from '../i18n/formatLocale.js'
import { i18n } from '../i18n/index.js'
import { toAgendaDate, withoutAllDayPin } from './agendaDate.js'
import { formatDateLong } from './date.js'

// One whole sentence per frequency, since the adjective sits after the noun in French and before it in
// English. The values are AgendaRecurrenceFrequency's on the server.
const SERIES_PREVIEW_KEYS = {
  daily: 'notification.preview.band_space_agenda_entry_created_daily',
  weekly: 'notification.preview.band_space_agenda_entry_created_weekly',
  monthly: 'notification.preview.band_space_agenda_entry_created_monthly',
  yearly: 'notification.preview.band_space_agenda_entry_created_yearly'
}

// An all day event covers a written day rather than an instant, and reading it as one shows the
// previous day to anyone west of UTC. The payload says which it is; a notification stored before
// that flag existed carries no key and reads as timed, which is what it was stored as, and the
// enricher puts the real flag back on any entry that still exists.
export function agendaEventDay(payload) {
  return formatDateLong(withoutAllDayPin(payload.event_datetime, payload.is_all_day === true))
}

/**
 * The feed's sentence for a new agenda entry: from its first date to its last for a series (#1374),
 * the single date otherwise, which is also what a notification stored before the recurrence keys
 * existed, or naming a frequency this client does not know, reads as.
 */
export function agendaEntryCreatedPreview(payload) {
  const { t } = i18n.global
  const frequency = payload.recurrence_frequency
  const seriesKey = Object.hasOwn(SERIES_PREVIEW_KEYS, frequency)
    ? SERIES_PREVIEW_KEYS[frequency]
    : null
  const start = toAgendaDate(payload.event_datetime, payload.is_all_day === true)
  // A bare `yyyy-MM-dd` reads as local midnight, so no timezone moves the last day.
  const until = payload.recurrence_until_date ? parseISO(payload.recurrence_until_date) : null

  if (seriesKey === null || start === null || until === null || !isValid(until)) {
    return t('notification.preview.band_space_agenda_entry_created', {
      entry: payload.entry_title,
      date: agendaEventDay(payload)
    })
  }

  const locale = dateFnsLocale()
  // « du 6 janvier au 30 juin 2027 »: the year once when the series starts and ends in it.
  const startPattern = start.getFullYear() === until.getFullYear() ? 'd MMMM' : 'd MMMM yyyy'

  return t(seriesKey, {
    entry: payload.entry_title,
    date: format(start, startPattern, { locale }),
    until: format(until, 'd MMMM yyyy', { locale })
  })
}
