import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { listenPlaylist, stepIndex, trackIndex, trackLabel } from './profileListen.js'

describe('listenPlaylist', () => {
  it('plays the musician media before the teacher media', () => {
    const profile = {
      musician: { media: [{ id: 'm1' }, { id: 'm2' }] },
      teacher: { media: [{ id: 't1' }] }
    }
    assert.deepEqual(
      listenPlaylist(profile).map((media) => media.id),
      ['m1', 'm2', 't1']
    )
  })

  it('is empty without a musician or teacher profile', () => {
    assert.deepEqual(listenPlaylist({ musician: null, teacher: null }), [])
    assert.deepEqual(listenPlaylist({ teacher: { media: [{ id: 't1' }] } }).length, 1)
  })
})

describe('trackLabel', () => {
  it('names a track by its title, or by its platform without one', () => {
    assert.equal(trackLabel({ title: 'Lueur', platform_label: 'Spotify' }), 'Lueur')
    assert.equal(trackLabel({ title: null, platform_label: 'SoundCloud' }), 'SoundCloud')
  })
})

describe('stepIndex', () => {
  it('moves one track and goes round at both ends', () => {
    assert.equal(stepIndex(3, 0, 1), 1)
    assert.equal(stepIndex(3, 2, 1), 0)
    assert.equal(stepIndex(3, 0, -1), 2)
    assert.equal(stepIndex(1, 0, 1), 0)
  })
})

describe('trackIndex', () => {
  it('finds a track by id, and says when it has left the list', () => {
    const tracks = [{ id: 'm1' }, { id: 't1' }]
    assert.equal(trackIndex(tracks, 't1'), 1)
    assert.equal(trackIndex(tracks, 'gone'), -1)
    assert.equal(trackIndex([], 'm1'), -1)
  })
})
