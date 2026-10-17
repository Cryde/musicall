import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'
import { i18n } from '../i18n/index.js'
import { formatAmount, formatEurosFromCents, formatEurosFromCentsTrimmed } from './currency.js'

afterEach(() => {
  i18n.global.locale.value = 'fr'
})

describe('formatEurosFromCents', () => {
  // Intl's French: a narrow no-break space between thousands, a no-break space before the sign.
  it('writes an amount in French, thousands grouped', () => {
    assert.equal(formatEurosFromCents(123450), '1\u202f234,50\u00a0€')
    assert.equal(formatEurosFromCents(5), '0,05\u00a0€')
    assert.equal(formatEurosFromCents(-98765), '-987,65\u00a0€')
  })

  it('writes an amount in British English, the sign first', () => {
    i18n.global.locale.value = 'en'

    assert.equal(formatEurosFromCents(123450), '€1,234.50')
    assert.equal(formatEurosFromCents(-98765), '-€987.65')
  })
})

describe('formatEurosFromCentsTrimmed', () => {
  it('drops the cents of a whole amount and keeps them otherwise', () => {
    assert.equal(formatEurosFromCentsTrimmed(5000), '50\u00a0€')
    assert.equal(formatEurosFromCentsTrimmed(4950), '49,50\u00a0€')
    i18n.global.locale.value = 'en'
    assert.equal(formatEurosFromCentsTrimmed(5000), '€50')
    assert.equal(formatEurosFromCentsTrimmed(4950), '€49.50')
  })
})

describe('formatAmount', () => {
  it('keeps its own writing in French: no grouping, a plain space', () => {
    assert.equal(formatAmount(123450), '1234,50 €')
    assert.equal(formatAmount(null), '0,00 €')
    assert.equal(formatAmount(-500), '-5,00 €')
  })

  it('writes English the way Intl does', () => {
    i18n.global.locale.value = 'en'

    assert.equal(formatAmount(123450), '€1,234.50')
    assert.equal(formatAmount(null), '€0.00')
  })
})
