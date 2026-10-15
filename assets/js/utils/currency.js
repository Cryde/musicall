import { intlLocale } from '../i18n/formatLocale.js'

// The API's ceiling on every finance amount (FinanceAmountLimit, #1045), in euros: the inputs stop
// there rather than letting someone type a figure the save would refuse.
export const MAX_AMOUNT_EUROS = 100_000_000

export function formatAmount(cents) {
  const value = cents ?? 0
  return `${(value / 100).toFixed(2).replace('.', ',')} \u20AC`
}

// Grouped thousands and no-break spaces, by the active locale: « 1 234,50 € ». `formatAmount` above
// is the finance module's older hand-rolled writing, kept as is so its amounts do not change.
export function formatEurosFromCents(cents) {
  return new Intl.NumberFormat(intlLocale(), { style: 'currency', currency: 'EUR' }).format(
    cents / 100
  )
}

export function centsToCurrency(cents) {
  return cents / 100
}

export function currencyToCents(euros) {
  return Math.round(euros * 100)
}

export function effectiveAmount(entry) {
  if (entry.amount != null) return entry.amount
  if (entry.amount_min != null && entry.amount_max != null) {
    return Math.round((entry.amount_min + entry.amount_max) / 2)
  }
  return 0
}
