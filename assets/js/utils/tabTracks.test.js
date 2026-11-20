import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { TRACK_FAMILIES, trackColor, trackFamily } from './tabTracks.js'

describe('trackFamily', () => {
  it('reads a percussion staff as drums, whatever its program', () => {
    assert.equal(trackFamily(0, true), TRACK_FAMILIES.DRUMS)
    assert.equal(trackFamily(30, true), TRACK_FAMILIES.DRUMS)
  })

  it('reads General MIDI programs 24 to 31 as guitars and 32 to 39 as basses', () => {
    assert.equal(trackFamily(24, false), TRACK_FAMILIES.GUITAR)
    assert.equal(trackFamily(30, false), TRACK_FAMILIES.GUITAR)
    assert.equal(trackFamily(31, false), TRACK_FAMILIES.GUITAR)
    assert.equal(trackFamily(32, false), TRACK_FAMILIES.BASS)
    assert.equal(trackFamily(39, false), TRACK_FAMILIES.BASS)
  })

  it('reads anything else as another instrument', () => {
    assert.equal(trackFamily(0, false), TRACK_FAMILIES.OTHER)
    assert.equal(trackFamily(23, false), TRACK_FAMILIES.OTHER)
    assert.equal(trackFamily(40, false), TRACK_FAMILIES.OTHER)
  })
})

describe('trackColor', () => {
  it('gives the first tracks different colours and cycles after six', () => {
    assert.notEqual(trackColor(0), trackColor(1))
    assert.equal(trackColor(6), trackColor(0))
  })
})
