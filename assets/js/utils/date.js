import { format, parseISO } from 'date-fns'

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
