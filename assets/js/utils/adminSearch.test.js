import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { percentOf, searchedForLabel } from './adminSearch.js'

describe('searchedForLabel', () => {
  it('reads the announce type as who was looked for', () => {
    assert.equal(searchedForLabel(2), 'Musiciens')
    assert.equal(searchedForLabel(1), 'Groupes')
    assert.equal(searchedForLabel(null), 'Tout')
  })
})

describe('percentOf', () => {
  it('rounds to a whole percent', () => {
    assert.equal(percentOf(1, 3), '33 %')
    assert.equal(percentOf(2, 2), '100 %')
  })

  it('says 0 % for an empty period', () => {
    assert.equal(percentOf(0, 0), '0 %')
  })
})
