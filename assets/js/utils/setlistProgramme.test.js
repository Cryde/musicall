import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  DEFAULT_COLUMNS,
  itemDuration,
  programmeRows,
  programmeSummary,
  readColumns,
  targetProgress,
  writeColumns
} from './setlistProgramme.js'

const song = (reference, override = null) => ({
  type: 'song',
  duration_override: override,
  song: { reference_duration: reference }
})
const pause = (duration = null) => ({ type: 'break', duration_override: duration, song: null })

function memoryStorage(initial = {}) {
  const values = { ...initial }
  return {
    getItem: (key) => values[key] ?? null,
    setItem: (key, value) => {
      values[key] = value
    },
    values
  }
}

describe('itemDuration', () => {
  it('takes the item override, then the song, then nothing', () => {
    assert.equal(itemDuration(song(200, 180)), 180)
    assert.equal(itemDuration(song(200)), 200)
    assert.equal(itemDuration(song(null)), null)
    assert.equal(itemDuration(pause()), null)
  })
})

describe('programmeRows', () => {
  it('numbers songs only and runs the total', () => {
    const rows = programmeRows([song(232), song(255), pause(90), song(218)])

    assert.deepEqual(
      rows.map(({ number, duration, cumulative, uncertain }) => [
        number,
        duration,
        cumulative,
        uncertain
      ]),
      [
        [1, 232, 232, false],
        [2, 255, 487, false],
        [null, 90, 577, false],
        [3, 218, 795, false]
      ]
    )
  })

  it('turns the total uncertain from the first song with no duration, not on a bare intermède', () => {
    const rows = programmeRows([song(200), pause(), song(null), song(100)])

    assert.deepEqual(
      rows.map(({ cumulative, uncertain, missingDuration }) => [
        cumulative,
        uncertain,
        missingDuration
      ]),
      [
        [200, false, false],
        [200, false, false],
        [200, true, true],
        [300, true, false]
      ]
    )
  })
})

describe('programmeSummary', () => {
  it('counts songs, intermèdes, the total and the songs missing a duration', () => {
    assert.deepEqual(
      programmeSummary([song(200), pause(45), song(null), { ...pause(), type: 'talk' }]),
      {
        songs: 2,
        intermissions: 2,
        total: 245,
        missingDurations: 1
      }
    )
  })

  it('answers an empty set', () => {
    assert.deepEqual(programmeSummary([]), {
      songs: 0,
      intermissions: 0,
      total: 0,
      missingDurations: 0
    })
  })
})

describe('targetProgress', () => {
  it('is nothing without a target', () => {
    assert.equal(targetProgress(600, null), null)
  })

  it('reports what is left, and an overrun past the target', () => {
    assert.deepEqual(targetProgress(1637, 2700), {
      ratio: 1637 / 2700,
      remaining: 1063,
      isOver: false
    })
    assert.deepEqual(targetProgress(3000, 2700), { ratio: 1, remaining: -300, isOver: true })
  })
})

describe('columns', () => {
  it('starts from the defaults', () => {
    assert.deepEqual(readColumns(memoryStorage()), DEFAULT_COLUMNS)
    assert.deepEqual(readColumns(null), DEFAULT_COLUMNS)
  })

  it('keeps a stored choice and fills what it does not know', () => {
    const storage = memoryStorage({
      'musicall.setlist.columns': '{"tempo":true,"tonality":false,"bogus":1}'
    })

    assert.deepEqual(readColumns(storage), { ...DEFAULT_COLUMNS, tempo: true, tonality: false })
  })

  it('falls back on corrupt or blocked storage', () => {
    assert.deepEqual(
      readColumns(memoryStorage({ 'musicall.setlist.columns': '{nope' })),
      DEFAULT_COLUMNS
    )
    const blocked = {
      getItem: () => {
        throw new Error('blocked')
      }
    }
    assert.deepEqual(readColumns(blocked), DEFAULT_COLUMNS)
  })

  it('round trips', () => {
    const storage = memoryStorage()
    writeColumns(storage, { ...DEFAULT_COLUMNS, cumulative: false })

    assert.deepEqual(readColumns(storage), { ...DEFAULT_COLUMNS, cumulative: false })
  })
})
