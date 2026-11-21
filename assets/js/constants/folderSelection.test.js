import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  isVirtualFolderId,
  listedFolderId,
  NO_FOLDER_LISTED,
  ROOT_FOLDER_ID,
  songFolderId,
  TRASH_FOLDER_ID,
  virtualFolderId,
  virtualFolderSongId,
  virtualFolderSource
} from './folderSelection.js'

/**
 * The Files panel used to open on every file in the space, so a selection was either a folder or it
 * was everything. Landing on the root added a third state, and the three read almost alike at a call
 * site: the root lists one place and holds null, no selection lists the whole space, and both are
 * falsy. What follows pins the three apart.
 *
 * Run with `npm test`.
 */

const SONG_ID = '0198c0de-dead-beef-cafe-000000000045'

describe('listedFolderId', () => {
  it('reads the root as no folder, the way a file at the root spells its own', () => {
    assert.equal(listedFolderId(ROOT_FOLDER_ID), null)
  })

  it('reads a folder as itself', () => {
    assert.equal(
      listedFolderId('0198c0de-dead-beef-cafe-000000000001'),
      '0198c0de-dead-beef-cafe-000000000001'
    )
  })

  it('has no folder for the flat listing of the whole space', () => {
    assert.equal(listedFolderId(null), NO_FOLDER_LISTED)
  })

  it('has no folder for a virtual source folder, which holds files from anywhere', () => {
    assert.equal(listedFolderId('virtual:note'), NO_FOLDER_LISTED)
  })

  it('has no folder for the trash, which keeps a file whatever folder it was in', () => {
    assert.equal(listedFolderId(TRASH_FOLDER_ID), NO_FOLDER_LISTED)
  })

  it('has no folder for a song under « Titres », whose files sit in any folder', () => {
    assert.equal(listedFolderId(songFolderId(SONG_ID)), NO_FOLDER_LISTED)
  })
})

describe('isVirtualFolderId', () => {
  it('recognises a virtual source folder', () => {
    assert.equal(isVirtualFolderId('virtual:setlist'), true)
  })

  it('recognises a song under « Titres », where nothing can be created either', () => {
    assert.equal(isVirtualFolderId(songFolderId(SONG_ID)), true)
  })

  it('leaves the real selections alone', () => {
    assert.equal(isVirtualFolderId(ROOT_FOLDER_ID), false)
    assert.equal(isVirtualFolderId(TRASH_FOLDER_ID), false)
    assert.equal(isVirtualFolderId('0198c0de-dead-beef-cafe-000000000001'), false)
    assert.equal(isVirtualFolderId(null), false)
  })
})

describe('virtualFolderSource', () => {
  /** The five the collection's `source` filter accepts, and the five the listener builds ids from. */
  const SOURCES = ['task', 'finance', 'note', 'song', 'setlist']

  it('returns the source a virtual folder groups', () => {
    for (const source of SOURCES) {
      assert.equal(virtualFolderSource(`virtual:${source}`), source)
    }
  })

  it('answers song for a song under « Titres », which lists part of that source', () => {
    assert.equal(virtualFolderSource(songFolderId(SONG_ID)), 'song')
  })

  it('returns null for anything that is not a virtual folder', () => {
    assert.equal(virtualFolderSource(ROOT_FOLDER_ID), null)
    assert.equal(virtualFolderSource(TRASH_FOLDER_ID), null)
    assert.equal(virtualFolderSource('0198c0de-dead-beef-cafe-000000000001'), null)
    assert.equal(virtualFolderSource(null), null)
  })
})

describe('virtualFolderId', () => {
  it('round trips with virtualFolderSource', () => {
    for (const source of ['task', 'finance', 'note', 'song', 'setlist']) {
      assert.equal(virtualFolderSource(virtualFolderId(source)), source)
    }
  })

  it('builds the id the folder collection publishes', () => {
    assert.equal(virtualFolderId('note'), 'virtual:note')
  })
})

describe('songFolderId', () => {
  it('builds the node of one song under the « Titres » virtual folder', () => {
    assert.equal(songFolderId(SONG_ID), `virtual:song:${SONG_ID}`)
  })
})

describe('virtualFolderSongId', () => {
  it('round trips with songFolderId', () => {
    assert.equal(virtualFolderSongId(songFolderId(SONG_ID)), SONG_ID)
  })

  it('has no song for « Titres » itself, which lists every song at once', () => {
    assert.equal(virtualFolderSongId(virtualFolderId('song')), null)
    assert.equal(virtualFolderSongId('virtual:song:'), null)
  })

  it('has no song for any other selection', () => {
    assert.equal(virtualFolderSongId('virtual:task'), null)
    assert.equal(virtualFolderSongId(`virtual:task:${SONG_ID}`), null)
    assert.equal(virtualFolderSongId(ROOT_FOLDER_ID), null)
    assert.equal(virtualFolderSongId(TRASH_FOLDER_ID), null)
    assert.equal(virtualFolderSongId(SONG_ID), null)
    assert.equal(virtualFolderSongId(null), null)
  })
})
