import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createSongSaveLock } from './songSaveLock.js'

describe('createSongSaveLock', () => {
  it('locks the other fields of a song while one saves, and frees them after', async () => {
    const lock = createSongSaveLock()
    let release
    const pending = lock.run('song-1', 'tempo', () => new Promise((resolve) => (release = resolve)))

    assert.equal(lock.isLocked('song-1', 'tonality'), true)
    assert.equal(lock.isLocked('song-1', 'tempo'), false)
    release('saved')
    assert.equal(await pending, 'saved')
    assert.equal(lock.isLocked('song-1', 'tonality'), false)
  })

  it('never makes another song wait', () => {
    const lock = createSongSaveLock()
    lock.run('song-1', 'tempo', () => new Promise(() => {}))

    assert.equal(lock.isLocked('song-2', 'tonality'), false)
  })

  it('frees the song when the save is refused, and rethrows the refusal', async () => {
    const lock = createSongSaveLock()

    await assert.rejects(
      lock.run('song-1', 'tempo', async () => {
        throw new Error('refused')
      }),
      /refused/
    )
    assert.equal(lock.isLocked('song-1', 'tonality'), false)
  })
})
