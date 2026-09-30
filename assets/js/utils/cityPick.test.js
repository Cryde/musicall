import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { resolveTypedCity } from './cityPick.js'

/**
 * Typing « Lyon » and submitting before picking a suggestion ran the search with no location at
 * all. These pin the fallback: the first suggestion, and nothing invented when there is none.
 *
 * Run with `npm test`.
 */

const LYON = { name: 'Lyon', context: 'Rhône, France', latitude: 45.76, longitude: 4.83 }
const LYONS = { name: 'Lyons-la-Forêt', context: 'Eure, France', latitude: 49.4, longitude: 1.47 }

describe('resolveTypedCity', () => {
  it('keeps a picked city', () => {
    assert.equal(resolveTypedCity(LYONS, [LYON]), LYONS)
  })

  it('takes the first suggestion for typed text', () => {
    assert.equal(resolveTypedCity('lyon', [LYON, LYONS]), LYON)
  })

  it('leaves the text alone when nothing matched', () => {
    assert.equal(resolveTypedCity('xyzzy', []), 'xyzzy')
  })

  it('does not guess from a single letter', () => {
    assert.equal(resolveTypedCity('l', [LYON]), 'l')
  })

  it('keeps an empty field empty', () => {
    assert.equal(resolveTypedCity(null, [LYON]), null)
  })
})
