import { formatAmount } from './currency.js'

/** An entry's amount as written in its lists: the exact figure, or its range. */
export function formatEntryAmount(entry) {
  if (entry.amount != null) return formatAmount(entry.amount)
  if (entry.amount_min != null && entry.amount_max != null) {
    return `${formatAmount(entry.amount_min)} - ${formatAmount(entry.amount_max)}`
  }
  return formatAmount(0)
}

/**
 * The same, signed by its type: « +600,00 € » in, « -120,00 € » out, where a phone row has no room
 * for the type badge. A range stays unsigned, since a minus in front of « 100 - 200 » would read as
 * a sum.
 */
export function formatSignedEntryAmount(entry) {
  const amount = formatEntryAmount(entry)
  if (entry.amount == null) return amount
  return `${entry.type === 'expense' ? '-' : '+'}${amount}`
}
