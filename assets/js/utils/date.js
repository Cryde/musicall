import { format, parseISO } from 'date-fns'
import { fr } from 'date-fns/locale'

export function formatDate(dateString) {
  return format(parseISO(dateString), 'dd/MM/yyyy HH:mm')
}

export function formatDateShort(dateString) {
  return format(parseISO(dateString), 'dd/MM/yyyy')
}

export function formatDateCompact(dateString) {
  if (!dateString) return ''
  const date = parseISO(dateString)
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' })
}

export function formatDateCompactWithYear(dateString) {
  if (!dateString) return ''
  const date = parseISO(dateString)
  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' })
}

// Spelled-out date, for copy that has to read as a sentence: « supprimé le 29 août 2026 ».
export function formatDateLong(dateString) {
  if (!dateString) return ''
  return format(parseISO(dateString), 'd MMMM yyyy', { locale: fr })
}
