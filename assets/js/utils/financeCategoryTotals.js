import { effectiveAmount } from './currency.js'

/**
 * A category's band entries, incomes and expenses kept apart (#1169). Added together, a 500 € cachet
 * and a 200 € expense read 700 €, and the share paid mixed money coming in with money going out.
 *
 * Personal entries are left out, as in the group totals: they belong to one member, not the band.
 *
 * @param {Array<{type: string, scope: string, status: string}>} entries
 * @returns {{income: {total: number, paid: number, percent: number}, expense: {total: number, paid: number, percent: number}}}
 *   amounts in cents
 */
export function financeCategoryTotals(entries) {
  const bandEntries = entries.filter((entry) => entry.scope !== 'personal')

  return {
    income: totalsOf(bandEntries.filter((entry) => entry.type === 'income')),
    expense: totalsOf(bandEntries.filter((entry) => entry.type === 'expense'))
  }
}

function totalsOf(entries) {
  const total = entries.reduce((sum, entry) => sum + effectiveAmount(entry), 0)
  const paid = entries
    .filter((entry) => entry.status === 'paid')
    .reduce((sum, entry) => sum + effectiveAmount(entry), 0)
  const percent = total === 0 ? 0 : Math.min(Math.round((paid / total) * 100), 100)

  return { total, paid, percent }
}
