import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  buildSongFileTree,
  canHoldSongSubfolder,
  songFolderDestinations,
  songFolderIdOf,
  songFolderNameOptions,
  songFolderPath,
  visibleSongFileRows
} from './songFileFolders.js'

const SONG = 'new45'
const folder = (id, name, parentId = null) => ({ id, name, parent_id: parentId })
const file = (id, folderId, songId = SONG) => ({
  id,
  attachments: [
    { source_type: 'task', source_id: 't1', song_folder_id: null },
    { source_type: 'song', source_id: songId, song_folder_id: folderId }
  ]
})

const FOLDERS = [
  folder('stems', 'Stems'),
  folder('drums', 'Batterie', 'stems'),
  folder('mics', 'Micros', 'drums'),
  folder('demos', 'Démos')
]

describe('songFolderIdOf', () => {
  it('reads the folder off the attachment of that song', () => {
    const shared = {
      attachments: [
        { source_type: 'song', source_id: 'ember', song_folder_id: 'ember-stems' },
        { source_type: 'song', source_id: SONG, song_folder_id: 'stems' }
      ]
    }

    assert.equal(songFolderIdOf(shared, SONG), 'stems')
    assert.equal(songFolderIdOf(shared, 'ember'), 'ember-stems')
  })

  it('is null at the root, for a file of another song, and before the field existed', () => {
    assert.equal(songFolderIdOf(file('a', null), SONG), null)
    assert.equal(songFolderIdOf(file('a', 'stems', 'ember'), SONG), null)
    assert.equal(
      songFolderIdOf({ attachments: [{ source_type: 'song', source_id: SONG }] }, SONG),
      null
    )
    assert.equal(songFolderIdOf({}, SONG), null)
  })
})

describe('buildSongFileTree', () => {
  it('nests the folders by name, puts each file in its folder and counts sub-folders in', () => {
    const tree = buildSongFileTree(
      FOLDERS,
      [file('bass', 'stems'), file('kick', 'drums'), file('oh', 'mics'), file('mix', null)],
      SONG
    )

    assert.deepEqual(
      tree.files.map((f) => f.id),
      ['mix']
    )
    const [demos, stems] = tree.folders
    assert.equal(demos.folder.id, 'demos')
    assert.equal(demos.fileCount, 0)
    assert.equal(stems.depth, 1)
    assert.deepEqual(
      stems.files.map((f) => f.id),
      ['bass']
    )
    assert.equal(stems.fileCount, 3)
    const [drums] = stems.folders
    assert.equal(drums.depth, 2)
    assert.equal(drums.fileCount, 2)
    assert.equal(drums.folders[0].depth, 3)
    assert.deepEqual(
      drums.folders[0].files.map((f) => f.id),
      ['oh']
    )
  })

  it('keeps at the root a file whose folder another member just deleted', () => {
    const tree = buildSongFileTree([folder('demos', 'Démos')], [file('bass', 'stems')], SONG)

    assert.deepEqual(
      tree.files.map((f) => f.id),
      ['bass']
    )
    assert.equal(tree.folders[0].fileCount, 0)
  })
})

describe('songFolderPath', () => {
  it('names the way down from the song root', () => {
    assert.deepEqual(songFolderPath(FOLDERS, 'mics'), [
      { id: 'stems', name: 'Stems' },
      { id: 'drums', name: 'Batterie' },
      { id: 'mics', name: 'Micros' }
    ])
  })

  it('is empty at the root and for a folder it does not know', () => {
    assert.deepEqual(songFolderPath(FOLDERS, null), [])
    assert.deepEqual(songFolderPath(FOLDERS, 'gone'), [])
  })
})

describe('canHoldSongSubfolder', () => {
  it('lets the first two levels hold a folder, not the third', () => {
    assert.equal(canHoldSongSubfolder(1), true)
    assert.equal(canHoldSongSubfolder(2), true)
    assert.equal(canHoldSongSubfolder(3), false)
  })
})

describe('songFolderDestinations', () => {
  it('lists every folder in tree order with its depth', () => {
    assert.deepEqual(songFolderDestinations(FOLDERS), [
      { id: 'demos', name: 'Démos', depth: 1 },
      { id: 'stems', name: 'Stems', depth: 1 },
      { id: 'drums', name: 'Batterie', depth: 2 },
      { id: 'mics', name: 'Micros', depth: 3 }
    ])
  })
})

describe('songFolderNameOptions', () => {
  const SUGGESTED = ['Tablatures', 'Stems', 'Démos', 'Partitions']

  it('offers the names the space uses first, then the suggested ones, each once whatever its case', () => {
    assert.deepEqual(songFolderNameOptions(['stems', 'Voix', 'Voix'], SUGGESTED, [], ''), [
      'stems',
      'Voix',
      'Tablatures',
      'Démos',
      'Partitions'
    ])
  })

  it('leaves out the names a sibling already has and narrows to what was typed', () => {
    assert.deepEqual(songFolderNameOptions(['Voix'], SUGGESTED, ['STEMS'], 'ta'), ['Tablatures'])
    assert.deepEqual(songFolderNameOptions(['Voix'], SUGGESTED, ['stems'], '  PAR '), [
      'Partitions'
    ])
  })
})

describe('visibleSongFileRows', () => {
  const bass = file('bass', 'stems')
  const kick = file('kick', 'drums')
  const tab = file('tab', null)
  const mix = file('mix', null)
  const tree = buildSongFileTree(FOLDERS, [bass, kick, tab, mix], SONG)
  const shape = (rows) =>
    rows.map((row) => `${row.level}:${row.type}:${row.node?.folder.id ?? row.file?.id ?? ''}`)

  it("shows closed folders, then the root files without the tab card's file", () => {
    assert.deepEqual(shape(visibleSongFileRows(tree, new Set(), tab)), [
      '0:folder:demos',
      '0:folder:stems',
      '0:file:mix'
    ])
  })

  it('opens a folder onto its sub-folders, then its files, one level in', () => {
    assert.deepEqual(shape(visibleSongFileRows(tree, new Set(['stems', 'drums']), tab)), [
      '0:folder:demos',
      '0:folder:stems',
      '1:folder:drums',
      '2:folder:mics',
      '2:file:kick',
      '1:file:bass',
      '0:file:mix'
    ])
  })

  it('tells the folder a drop on each row lands in', () => {
    const rows = visibleSongFileRows(tree, new Set(['demos', 'stems']), tab)
    assert.deepEqual(
      rows.map((row) => `${row.type}:${row.folderId}`),
      ['folder:demos', 'empty:demos', 'folder:stems', 'folder:drums', 'file:stems', 'file:null']
    )
  })

  it('says an open folder is empty', () => {
    assert.deepEqual(shape(visibleSongFileRows(tree, new Set(['demos']), null)), [
      '0:folder:demos',
      '1:empty:',
      '0:folder:stems',
      '0:file:tab',
      '0:file:mix'
    ])
  })

  it("keeps the tab card's file in its folder", () => {
    const tabInFolder = file('gp', 'demos')
    const rows = visibleSongFileRows(
      buildSongFileTree(FOLDERS, [tabInFolder], SONG),
      new Set(['demos']),
      tabInFolder
    )
    assert.deepEqual(shape(rows), ['0:folder:demos', '1:file:gp', '0:folder:stems'])
  })
})
