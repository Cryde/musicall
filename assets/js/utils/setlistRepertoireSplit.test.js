import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { splitRepertoire } from './setlistRepertoireSplit.js'

const songs = [
  { id: 'a', title: 'Nuit blanche' },
  { id: 'b', title: 'Les Ondes' },
  { id: 'c', title: 'Marée basse' }
]
const items = [
  { type: 'song', song: { id: 'a' } },
  { type: 'break', song: null },
  { type: 'song', song: { id: 'a' } }
]

describe('splitRepertoire', () => {
  it('puts the songs of the set apart, once each', () => {
    const { available, inSetlist } = splitRepertoire(songs, items)
    assert.deepEqual(
      available.map((song) => song.id),
      ['b', 'c']
    )
    assert.deepEqual(
      inSetlist.map((song) => song.id),
      ['a']
    )
  })

  it('searches the titles whatever their case', () => {
    const { available, inSetlist } = splitRepertoire(songs, items, '  MARÉE ')
    assert.deepEqual(
      available.map((song) => song.id),
      ['c']
    )
    assert.deepEqual(inSetlist, [])
  })

  it('leaves everything available for an empty set', () => {
    assert.equal(splitRepertoire(songs, []).available.length, 3)
  })
})
