import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { financeCategoryTotals } from './financeCategoryTotals.js'

/** A finance category's totals, incomes and expenses apart (#1169). Run with `npm test`. */
function entry(type, amount, status = 'planned', scope = 'band') {
  return { type, amount, status, scope }
}

describe('financeCategoryTotals', () => {
  it('keeps a cachet and an expense apart rather than adding them up', () => {
    const totals = financeCategoryTotals([entry('income', 50000), entry('expense', 20000)])

    assert.deepEqual(totals, {
      income: { total: 50000, paid: 0, percent: 0 },
      expense: { total: 20000, paid: 0, percent: 0 }
    })
  })

  it('works out the share paid per type', () => {
    const totals = financeCategoryTotals([
      entry('income', 50000, 'paid'),
      entry('income', 50000),
      entry('expense', 20000, 'paid')
    ])

    assert.deepEqual(totals.income, { total: 100000, paid: 50000, percent: 50 })
    assert.deepEqual(totals.expense, { total: 20000, paid: 20000, percent: 100 })
  })

  it('leaves personal entries out', () => {
    const totals = financeCategoryTotals([
      entry('expense', 20000),
      entry('expense', 9900, 'paid', 'personal')
    ])

    assert.deepEqual(totals.expense, { total: 20000, paid: 0, percent: 0 })
  })

  it('reads a category of personal entries only as nothing on either side', () => {
    const totals = financeCategoryTotals([entry('income', 9900, 'paid', 'personal')])

    assert.deepEqual(totals.income, { total: 0, paid: 0, percent: 0 })
  })

  it('counts a committed entry in the total but not in what is paid', () => {
    const totals = financeCategoryTotals([
      entry('expense', 20000, 'committed'),
      entry('expense', 20000, 'paid')
    ])

    assert.deepEqual(totals.expense, { total: 40000, paid: 20000, percent: 50 })
  })

  it('counts a range at its midpoint', () => {
    const range = {
      type: 'income',
      amount: null,
      amount_min: 10000,
      amount_max: 30000,
      status: 'planned',
      scope: 'band'
    }

    assert.equal(financeCategoryTotals([range]).income.total, 20000)
  })

  it('reads an empty category as nothing on either side', () => {
    assert.deepEqual(financeCategoryTotals([]), {
      income: { total: 0, paid: 0, percent: 0 },
      expense: { total: 0, paid: 0, percent: 0 }
    })
  })
})
