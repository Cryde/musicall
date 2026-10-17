import { forActiveLocale, intlLocale } from '../i18n/formatLocale.js'

// The API's ceiling on every finance amount (FinanceAmountLimit, #1045), in euros: the inputs stop
// there rather than letting someone type a figure the save would refuse.
export const MAX_AMOUNT_EUROS = 100_000_000

// Grouped thousands and no-break spaces, by the active locale: « 1 234,50 € ».
export function formatEurosFromCents(cents) {
  return new Intl.NumberFormat(intlLocale(), { style: 'currency', currency: 'EUR' }).format(
    cents / 100
  )
}

// The same, with the cents only when there are some: "€50", "€49.50".
export function formatEurosFromCentsTrimmed(cents) {
  const fractionDigits = cents % 100 === 0 ? 0 : 2
  return new Intl.NumberFormat(intlLocale(), {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: fractionDigits,
    maximumFractionDigits: fractionDigits
  }).format(cents / 100)
}

// The finance module's older hand-rolled French, no grouping and a plain space, kept as is so its
// amounts do not change. Another language has no such history and takes the Intl writing.
function frenchAmount(cents) {
  return `${(cents / 100).toFixed(2).replace('.', ',')} \u20AC`
}

const AMOUNT_WRITERS = { fr: frenchAmount, en: formatEurosFromCents }

export function formatAmount(cents) {
  return forActiveLocale(AMOUNT_WRITERS)(cents ?? 0)
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
