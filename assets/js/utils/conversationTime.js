import { differenceInCalendarDays, format, startOfDay } from 'date-fns'
import { i18n } from '../i18n/index.js'
import { formatDateCompact } from './date.js'

/**
 * When a conversation last spoke, short enough for a row: « 09:41 » today, « Hier », then « 3 nov. »,
 * as the app's inbox has it. Empty without a date, so a conversation with no message yet shows
 * nothing instead of throwing.
 *
 * @param {string | Date | null | undefined} value
 * @param {Date} [now]
 * @returns {string}
 */
export function conversationTimeLabel(value, now = new Date()) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  const days = differenceInCalendarDays(startOfDay(now), startOfDay(date))
  if (days === 0) return format(date, 'HH:mm')
  if (days === 1) return i18n.global.t('message.time.yesterday')
  return formatDateCompact(date)
}
