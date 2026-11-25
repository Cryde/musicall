import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { headlineOffer, orderedSpecialities } from './servicePage.js'

describe('orderedSpecialities', () => {
  it('keeps the kind order and drops what the kind does not know', () => {
    assert.deepEqual(
      orderedSpecialities(['mixing', 'mastering', 'editing'], ['editing', 'nope', 'mixing']),
      ['mixing', 'editing']
    )
    assert.deepEqual(orderedSpecialities(['concert', 'studio'], undefined), [])
  })
})

describe('headlineOffer', () => {
  it('leads with the cheapest price per track, even when a day costs less', () => {
    const offers = [
      { id: 'day', unit: 'day', price: 6000 },
      { id: 'mix', unit: 'track', price: 8000 },
      { id: 'master', unit: 'track', price: 2500 }
    ]
    assert.equal(headlineOffer(offers).id, 'master')
  })

  it('falls back on the cheapest of any unit without a price per track', () => {
    const offers = [
      { id: 'ep', unit: 'flat', price: 45000 },
      { id: 'hour', unit: 'hour', price: 4000 }
    ]
    assert.equal(headlineOffer(offers).id, 'hour')
  })

  it('has nothing to say without offers', () => {
    assert.equal(headlineOffer([]), null)
    assert.equal(headlineOffer(undefined), null)
  })
})
