import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { headlineOffer, MAX_GEAR, parseGear } from './soundEngineerPage.js'

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

describe('parseGear', () => {
  it('splits on commas and lines, trims, and drops empties and repeats', () => {
    assert.deepEqual(parseGear(' Reaper, UAD Apollo x8\nNeumann U87,, Reaper \n'), [
      'Reaper',
      'UAD Apollo x8',
      'Neumann U87'
    ])
  })

  it('keeps at most the limit the API allows', () => {
    const text = Array.from({ length: MAX_GEAR + 5 }, (_, i) => `Item ${i}`).join(',')
    assert.equal(parseGear(text).length, MAX_GEAR)
  })
})
