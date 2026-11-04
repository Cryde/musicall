import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { foldForSearch } from './searchText.js'

/**
 * Text as a search compares it. Run with `npm test`.
 */

describe('foldForSearch', () => {
  it('drops accents and case and trims', () => {
    assert.equal(foldForSearch('  Léa Écrit À Noël '), 'lea ecrit a noel')
  })

  it('reads nothing as an empty text', () => {
    assert.equal(foldForSearch(null), '')
    assert.equal(foldForSearch(undefined), '')
  })
})
