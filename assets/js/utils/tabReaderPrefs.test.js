import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  DEFAULT_PANEL,
  PANEL_TABS,
  REMEMBERED_FILES,
  readPanel,
  readShownTracks,
  writePanel,
  writeShownTracks
} from './tabReaderPrefs.js'

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

const blocked = {
  getItem: () => {
    throw new Error('blocked')
  },
  setItem: () => {
    throw new Error('blocked')
  }
}

describe('the panel', () => {
  it('opens on the structure by default', () => {
    assert.deepEqual(readPanel(memoryStorage()), { visible: true, tab: PANEL_TABS.STRUCTURE })
    assert.deepEqual(readPanel(null), DEFAULT_PANEL)
  })

  it('round trips', () => {
    const storage = memoryStorage()
    writePanel(storage, { visible: false, tab: PANEL_TABS.TRACKS })

    assert.deepEqual(readPanel(storage), { visible: false, tab: PANEL_TABS.TRACKS })
  })

  it('keeps the defaults for what is corrupt, unknown or blocked', () => {
    assert.deepEqual(readPanel(memoryStorage({ 'musicall.tabs.panel': '{nope' })), DEFAULT_PANEL)
    assert.deepEqual(
      readPanel(memoryStorage({ 'musicall.tabs.panel': '{"visible":"yes","tab":"mixer"}' })),
      DEFAULT_PANEL
    )
    assert.deepEqual(readPanel(blocked), DEFAULT_PANEL)
    assert.doesNotThrow(() => writePanel(blocked, DEFAULT_PANEL))
  })
})

describe('the shown tracks', () => {
  it('remembers them per file', () => {
    const storage = memoryStorage()
    writeShownTracks(storage, 'file-a', [2])
    writeShownTracks(storage, 'file-b', [0, 1])

    assert.deepEqual(readShownTracks(storage, 'file-a', 4), [2])
    assert.deepEqual(readShownTracks(storage, 'file-b', 4), [0, 1])
    assert.equal(readShownTracks(storage, 'file-c', 4), null)
  })

  it('drops tracks a new version of the file no longer has', () => {
    const storage = memoryStorage()
    writeShownTracks(storage, 'file-a', [1, 3])

    assert.deepEqual(readShownTracks(storage, 'file-a', 3), [1])
    assert.equal(readShownTracks(storage, 'file-a', 1), null)
  })

  it('cleans what it reads: duplicates, order, values that are not track numbers', () => {
    const storage = memoryStorage({ 'musicall.tabs.tracks': '[["file-a",[2,0,2,-1,"1",1.5]]]' })

    assert.deepEqual(readShownTracks(storage, 'file-a', 4), [0, 2])
  })

  it('keeps the most recent files only', () => {
    const storage = memoryStorage()
    for (let n = 0; n <= REMEMBERED_FILES; n++) writeShownTracks(storage, `file-${n}`, [0])

    assert.equal(readShownTracks(storage, 'file-0', 2), null)
    assert.deepEqual(readShownTracks(storage, `file-${REMEMBERED_FILES}`, 2), [0])
    assert.equal(JSON.parse(storage.values['musicall.tabs.tracks']).length, REMEMBERED_FILES)
  })

  it('moves a file written again to the front instead of listing it twice', () => {
    const storage = memoryStorage()
    writeShownTracks(storage, 'file-a', [0])
    writeShownTracks(storage, 'file-b', [0])
    writeShownTracks(storage, 'file-a', [1])

    assert.deepEqual(
      JSON.parse(storage.values['musicall.tabs.tracks']).map((entry) => entry[0]),
      ['file-a', 'file-b']
    )
  })

  it('answers null on corrupt or blocked storage', () => {
    assert.equal(
      readShownTracks(memoryStorage({ 'musicall.tabs.tracks': '{"a":1}' }), 'a', 2),
      null
    )
    assert.equal(readShownTracks(blocked, 'a', 2), null)
    assert.doesNotThrow(() => writeShownTracks(blocked, 'a', [0]))
  })
})
