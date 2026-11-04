import { format, parseISO } from 'date-fns'
import { dateFnsLocale, intlLocale } from '../i18n/formatLocale.js'

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

// Spelled-out date, for copy that has to read as a sentence: « supprimé le 29 août 2026 ».
export function formatDateLong(dateString) {
  if (!dateString) return ''
  return format(parseISO(dateString), 'd MMMM yyyy', { locale: dateFnsLocale() })
}
