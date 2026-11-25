import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { offerRow, offersPayload } from './serviceOffers.js'

describe('offersPayload', () => {
  it('sends euros as cents, trims, and drops a row without a title', () => {
    assert.deepEqual(
      offersPayload([
        { unit: 'track', title: " Mixage d'un titre ", description: ' ', price: 80 },
        { unit: 'flat', title: '', description: 'oublié', price: 10 },
        { unit: 'flat', title: 'EP', description: 'Mixage et master', price: 449.99 },
        { unit: 'hour', title: 'Une heure', description: '', price: null }
      ]),
      [
        { unit: 'track', title: "Mixage d'un titre", description: null, price: 8000 },
        { unit: 'flat', title: 'EP', description: 'Mixage et master', price: 44999 },
        { unit: 'hour', title: 'Une heure', description: null, price: null }
      ]
    )
  })
})

describe('offerRow', () => {
  it('opens a stored offer in euros, each row with a key of its own', () => {
    const stored = offerRow({
      id: 'a',
      unit: 'event',
      title: 'Un concert',
      description: null,
      price: 25050
    })
    const fresh = offerRow({ unit: 'hour' })
    assert.deepEqual(
      { ...stored, key: undefined },
      { key: undefined, unit: 'event', title: 'Un concert', description: '', price: 250.5 }
    )
    assert.deepEqual(
      { ...fresh, key: undefined },
      { key: undefined, unit: 'hour', title: '', description: '', price: null }
    )
    assert.notEqual(stored.key, fresh.key)
  })

  it('saves back what it opened', () => {
    assert.deepEqual(
      offersPayload([offerRow({ unit: 'flat', title: 'EP', description: 'Mixage', price: 45000 })]),
      [{ unit: 'flat', title: 'EP', description: 'Mixage', price: 45000 }]
    )
  })
})
