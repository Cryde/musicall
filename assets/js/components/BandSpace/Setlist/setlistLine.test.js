import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { setlistLine } from './setlistLine.js'

// The translate stand-in writes the key, its duration and its count, enough to see which was picked.
const t = (key, params, count) =>
  typeof params === 'number' ? `${key}|${params}` : `${key}|${params?.duration}|${count}`

const song = (seconds) => ({
  type: 'song',
  duration_override: null,
  song: { reference_duration: seconds }
})

describe('setlistLine', () => {
  it('says a set without a song is empty', () => {
    assert.equal(
      setlistLine({ items: [] }, t),
      'band_space_setlist.sidebar.setlist_empty|undefined|undefined'
    )
  })

  it('gives the count and the length when durations are known', () => {
    assert.equal(
      setlistLine({ items: [song(180), song(120)] }, t),
      'band_space_setlist.sidebar.song_count_with_duration|5:00|2'
    )
  })

  it('gives the count alone without any duration', () => {
    assert.equal(setlistLine({ items: [song(null)] }, t), 'band_space_setlist.sidebar.song_count|1')
  })
})
