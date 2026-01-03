import { format, parseISO } from 'date-fns'

export function formatDate(dateString) {
  return format(parseISO(dateString), 'dd/MM/yyyy HH:mm')
}

export function formatDateShort(dateString) {
  return format(parseISO(dateString), 'dd/MM/yyyy')
}
