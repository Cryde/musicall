import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { filterCounts, filterSongs, hasMissingInfo } from './repertoireFilters.js'

const song = (title, fields = {}) => ({
  title,
  tonality: 'Am',
  tempo: 120,
  reference_duration: 200,
  has_lyrics: true,
  ...fields
})

const songs = [
  song('Neon Tide'),
  song('Kite Season', { has_lyrics: false }),
  song('Satellite Heart', { tempo: null }),
  song('Static Bloom', { has_lyrics: false, tonality: null })
]

describe('hasMissingInfo', () => {
  it('is any of key, BPM or duration missing', () => {
    assert.equal(hasMissingInfo(song('A')), false)
    assert.equal(hasMissingInfo(song('A', { reference_duration: null })), true)
  })
})

describe('filterSongs', () => {
  const titles = (filters) => filterSongs(songs, filters).map((s) => s.title)

  it('searches the title, ignoring case and spaces around', () => {
    assert.deepEqual(titles({ query: '  neon ' }), ['Neon Tide'])
  })

  it('narrows by each chip, and by both together', () => {
    assert.deepEqual(titles({ withoutLyrics: true }), ['Kite Season', 'Static Bloom'])
    assert.deepEqual(titles({ missingInfo: true }), ['Satellite Heart', 'Static Bloom'])
    assert.deepEqual(titles({ withoutLyrics: true, missingInfo: true }), ['Static Bloom'])
  })

  it('keeps everything with no filter', () => {
    assert.equal(filterSongs(songs).length, 4)
  })
})

describe('filterCounts', () => {
  it('counts over the whole repertoire', () => {
    assert.deepEqual(filterCounts(songs), { withoutLyrics: 2, missingInfo: 2 })
  })
})
