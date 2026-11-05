import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'
import { i18n } from '../i18n/index.js'
import { formatEntryAmount, formatSignedEntryAmount } from './financeEntryAmount.js'

afterEach(() => {
  i18n.global.locale.value = 'fr'
})

describe('formatEntryAmount', () => {
  it('writes an exact amount', () => {
    assert.equal(formatEntryAmount({ amount: 12000 }), '120,00 €')
  })

  it('writes a range', () => {
    assert.equal(
      formatEntryAmount({ amount: null, amount_min: 10000, amount_max: 20000 }),
      '100,00 € - 200,00 €'
    )
  })

  it('writes zero when there is neither', () => {
    assert.equal(formatEntryAmount({ amount: null, amount_min: 10000, amount_max: null }), '0,00 €')
  })
})

describe('formatSignedEntryAmount', () => {
  it('signs an income with a plus', () => {
    assert.equal(formatSignedEntryAmount({ type: 'income', amount: 60000 }), '+600,00 €')
  })

  it('signs an expense with a minus', () => {
    assert.equal(formatSignedEntryAmount({ type: 'expense', amount: 12000 }), '-120,00 €')
  })

  it('leaves a range unsigned', () => {
    assert.equal(
      formatSignedEntryAmount({
        type: 'expense',
        amount: null,
        amount_min: 10000,
        amount_max: 20000
      }),
      '100,00 € - 200,00 €'
    )
  })

  it('signs in English too', () => {
    i18n.global.locale.value = 'en'
    assert.equal(formatSignedEntryAmount({ type: 'expense', amount: 12000 }), '-€120.00')
  })
})
