import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { holdSongFiles, takeSongFiles } from './pendingSongFiles.js'

describe('pendingSongFiles', () => {
  it('hands the files held for a song to its page, once', () => {
    const files = [{ name: 'New 46.gp' }, { name: 'idée.m4a' }]
    holdSongFiles('song-1', files)

    assert.deepEqual(takeSongFiles('song-1'), files)
    assert.deepEqual(takeSongFiles('song-1'), [])
  })

  it('keeps each song to its own files', () => {
    holdSongFiles('song-1', [{ name: 'a.gp' }])
    holdSongFiles('song-2', [{ name: 'b.gp' }])

    assert.deepEqual(takeSongFiles('song-2'), [{ name: 'b.gp' }])
    assert.deepEqual(takeSongFiles('song-1'), [{ name: 'a.gp' }])
  })

  it('holds nothing for a song created without files', () => {
    holdSongFiles('song-3', [])

    assert.deepEqual(takeSongFiles('song-3'), [])
  })
})
