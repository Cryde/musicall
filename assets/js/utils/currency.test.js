import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { formatAmount, formatEurosFromCents } from './currency.js'

describe('formatEurosFromCents', () => {
  // Intl's French: a narrow no-break space between thousands, a no-break space before the sign.
  it('writes an amount in French, thousands grouped', () => {
    assert.equal(formatEurosFromCents(123450), '1\u202f234,50\u00a0€')
    assert.equal(formatEurosFromCents(5), '0,05\u00a0€')
    assert.equal(formatEurosFromCents(-98765), '-987,65\u00a0€')
  })
})

describe('formatAmount', () => {
  it('keeps its own writing: no grouping, a plain space', () => {
    assert.equal(formatAmount(123450), '1234,50 €')
    assert.equal(formatAmount(null), '0,00 €')
  })
})
