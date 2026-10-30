import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { filterLyrics, isLyricsReadOnly, linkableSongs, lyricsCounts } from './lyricsList.js'

/** The « Paroles » list (#1261). Run with `npm test`. */
const lyrics = (title, song = null, fields = {}) => ({ id: title, title, song, ...fields })
const song = (id, fields = {}) => ({ id, title: id, archive_datetime: null, ...fields })

const list = [
  lyrics('Neon Tide', { id: 'neon', archive_datetime: null }),
  lyrics('Kite Season'),
  lyrics('Static Bloom', { id: 'static', archive_datetime: '2026-10-01T10:00:00+00:00' }),
  lyrics('Night Draft')
]

describe('filterLyrics', () => {
  const titles = (filters) => filterLyrics(list, filters).map((item) => item.title)

  it('searches the title, ignoring case and spaces around', () => {
    assert.deepEqual(titles({ query: '  NEON ' }), ['Neon Tide'])
  })

  it('keeps the linked ones, or the unlinked ones', () => {
    assert.deepEqual(titles({ filter: 'linked' }), ['Neon Tide', 'Static Bloom'])
    assert.deepEqual(titles({ filter: 'unlinked' }), ['Kite Season', 'Night Draft'])
  })

  it('narrows by the search and the filter together', () => {
    assert.deepEqual(titles({ query: 'n', filter: 'unlinked' }), ['Kite Season', 'Night Draft'])
    assert.deepEqual(titles({ query: 'night', filter: 'linked' }), [])
  })

  it('keeps everything with no filter', () => {
    assert.equal(filterLyrics(list).length, 4)
  })
})

describe('lyricsCounts', () => {
  it('counts over the whole list', () => {
    assert.deepEqual(lyricsCounts(list), { all: 4, linked: 2, unlinked: 2 })
  })
})

describe('linkableSongs', () => {
  it('leaves out a song some lyrics are linked to, and a song in the trash', () => {
    const songs = [song('neon'), song('free'), song('retired', { archive_datetime: '2026-09-01' })]
    assert.deepEqual(
      linkableSongs(songs, list).map((s) => s.id),
      ['free']
    )
  })

  it('leaves out a song linked to empty lyrics, which has_lyrics reports as without lyrics', () => {
    const songs = [song('draft', { has_lyrics: false })]
    assert.deepEqual(linkableSongs(songs, [lyrics('Empty', { id: 'draft' })]), [])
  })
})

describe('isLyricsReadOnly', () => {
  it('is lyrics in the trash, or on a song in the trash', () => {
    assert.equal(isLyricsReadOnly(list[0]), false)
    assert.equal(isLyricsReadOnly(list[1]), false)
    assert.equal(isLyricsReadOnly(list[2]), true)
    assert.equal(isLyricsReadOnly(lyrics('Gone', null, { archive_datetime: '2026-10-02' })), true)
  })

  it('is false while nothing is loaded', () => {
    assert.equal(isLyricsReadOnly(null), false)
  })
})
