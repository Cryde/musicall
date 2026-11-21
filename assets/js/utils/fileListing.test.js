import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  NO_FOLDER_LISTED,
  ROOT_FOLDER_ID,
  songFolderId,
  TRASH_FOLDER_ID
} from '../constants/folderSelection.js'
import { i18n } from '../i18n/index.js'
import {
  activeLocationLabel,
  canFileSitAtRoot,
  canHoldSubfolder,
  fileListingParams,
  fileLocation,
  fileVirtualFolders,
  findFolder,
  folderFileCountLabel,
  folderPathOf,
  folderSelectOptions,
  isSearchActive,
  listedFolderOfRows,
  rootDestinationRefusal,
  treeHoldsFolder,
  uploadBelongsInListing,
  virtualFolderName
} from './fileListing.js'

/**
 * The panel opens on the root, so every listing is now scoped to one place unless something says
 * otherwise, and a search is that something: it has to escape the folder on screen, or a member
 * searching from inside a folder would be told a file they can see in « Tous les fichiers » does not
 * exist. What follows pins the scope rules, which is the half of this a browser check reads worst.
 *
 * Run with `npm test`.
 */

const FOLDER_ID = '0198c0de-dead-beef-cafe-000000000001'
const SONG_ID = '0198c0de-dead-beef-cafe-000000000045'

const filters = (overrides = {}) => ({
  query: '',
  mime: null,
  tagId: null,
  source: null,
  sort: 'date',
  order: 'desc',
  ...overrides
})

describe('isSearchActive', () => {
  it('is false for an empty box and for whitespace only', () => {
    assert.equal(isSearchActive(filters()), false)
    assert.equal(isSearchActive(filters({ query: '   ' })), false)
  })

  it('is true once something has been typed', () => {
    assert.equal(isSearchActive(filters({ query: 'contrat' })), true)
  })
})

describe('listedFolderOfRows', () => {
  it('is the selected place while no search is running', () => {
    assert.equal(listedFolderOfRows(ROOT_FOLDER_ID, filters()), null)
    assert.equal(listedFolderOfRows(FOLDER_ID, filters()), FOLDER_ID)
  })

  it('is nowhere in particular for the flat listing, a virtual folder and the trash', () => {
    assert.equal(listedFolderOfRows(null, filters()), NO_FOLDER_LISTED)
    assert.equal(listedFolderOfRows('virtual:note', filters()), NO_FOLDER_LISTED)
    assert.equal(listedFolderOfRows(TRASH_FOLDER_ID, filters()), NO_FOLDER_LISTED)
  })

  it('is nowhere in particular during a search, whatever the sidebar has selected', () => {
    const searching = filters({ query: 'contrat' })
    assert.equal(listedFolderOfRows(ROOT_FOLDER_ID, searching), NO_FOLDER_LISTED)
    assert.equal(listedFolderOfRows(FOLDER_ID, searching), NO_FOLDER_LISTED)
  })
})

describe('fileListingParams', () => {
  it('asks the collection for the root of the tree by its reserved value', () => {
    assert.deepEqual(fileListingParams(ROOT_FOLDER_ID, filters(), 1), {
      page: 1,
      itemsPerPage: 50,
      sort: 'date',
      order: 'desc',
      folderId: 'root'
    })
  })

  it('sends no folder at all for the flat listing of the whole space', () => {
    const params = fileListingParams(null, filters(), 2)
    assert.equal('folderId' in params, false)
    assert.equal(params.page, 2)
  })

  it('drops the folder scope while searching, so the search covers the whole space', () => {
    const params = fileListingParams(FOLDER_ID, filters({ query: ' contrat ' }), 1)
    assert.equal('folderId' in params, false)
    assert.equal(params.query, 'contrat')
  })

  it('keeps a virtual folder scoped to its source while searching', () => {
    const params = fileListingParams('virtual:setlist', filters({ query: 'plan' }), 1)
    assert.equal(params.source, 'setlist')
    assert.equal('folderId' in params, false)
    assert.equal(params.query, 'plan')
  })

  it('turns each virtual folder into its source filter', () => {
    for (const source of ['task', 'finance', 'note', 'song', 'setlist']) {
      assert.equal(fileListingParams(`virtual:${source}`, filters(), 1).source, source)
    }
  })

  it('narrows « Chansons » to one song for the node of that song', () => {
    assert.deepEqual(fileListingParams(songFolderId(SONG_ID), filters(), 2), {
      page: 2,
      itemsPerPage: 50,
      sort: 'date',
      order: 'desc',
      source: 'song',
      sourceId: SONG_ID
    })
  })

  it('keeps the node of a song to that song while searching, like any virtual folder', () => {
    const params = fileListingParams(songFolderId(SONG_ID), filters({ query: 'grille' }), 1)
    assert.equal(params.source, 'song')
    assert.equal(params.sourceId, SONG_ID)
    assert.equal(params.query, 'grille')
  })

  it('names no song for « Chansons » itself, which lists every song', () => {
    assert.equal('sourceId' in fileListingParams('virtual:song', filters(), 1), false)
  })

  it('carries the tag and type filters alongside the folder', () => {
    const params = fileListingParams(FOLDER_ID, filters({ tagId: 'tag-1', mime: 'audio/' }), 1)
    assert.equal(params.folderId, FOLDER_ID)
    assert.equal(params.tagId, 'tag-1')
    assert.equal(params.mime, 'audio/')
  })

  it('ignores every filter in the trash, which has no filter bar to clear them from', () => {
    const params = fileListingParams(
      TRASH_FOLDER_ID,
      filters({ query: 'contrat', tagId: 'tag-1', mime: 'audio/' }),
      3
    )
    assert.deepEqual(params, { archived: true, page: 3, itemsPerPage: 50 })
  })
})

describe('folderFileCountLabel', () => {
  it('counts in French, singular and plural', () => {
    assert.equal(folderFileCountLabel(1), '1 fichier')
    assert.equal(folderFileCountLabel(12), '12 fichiers')
  })

  it('says nothing for a folder with no file of its own', () => {
    assert.equal(folderFileCountLabel(0), null)
    assert.equal(folderFileCountLabel(undefined), null)
    assert.equal(folderFileCountLabel(null), null)
  })
})

describe('treeHoldsFolder', () => {
  const tree = [
    { id: 'live', children: [{ id: '2026', children: [{ id: 'paris', children: [] }] }] },
    { id: 'riders', children: [] }
  ]

  it('finds a folder at any depth', () => {
    assert.equal(treeHoldsFolder(tree, 'live'), true)
    assert.equal(treeHoldsFolder(tree, '2026'), true)
    assert.equal(treeHoldsFolder(tree, 'paris'), true)
    assert.equal(treeHoldsFolder(tree, 'riders'), true)
  })

  it('does not find a folder the tree no longer holds', () => {
    assert.equal(treeHoldsFolder(tree, 'deleted'), false)
    assert.equal(treeHoldsFolder([], 'live'), false)
  })

  it('is false for the root and for anything that is not a folder id', () => {
    assert.equal(treeHoldsFolder(tree, null), false)
    assert.equal(treeHoldsFolder(undefined, 'live'), false)
  })
})

describe('folderPathOf', () => {
  const tree = [
    {
      id: 'live',
      name: 'Live',
      children: [
        { id: '2026', name: '2026', children: [{ id: 'paris', name: 'paris', children: [] }] }
      ]
    },
    { id: 'riders', name: 'Riders', children: [] }
  ]

  it('names every folder down to the one asked for', () => {
    assert.deepEqual(folderPathOf(tree, 'paris'), [
      { id: 'live', name: 'Live' },
      { id: '2026', name: '2026' },
      { id: 'paris', name: 'paris' }
    ])
  })

  it('is one segment for a folder at the top', () => {
    assert.deepEqual(folderPathOf(tree, 'riders'), [{ id: 'riders', name: 'Riders' }])
  })

  it('is empty for the root and for a folder the tree does not hold', () => {
    assert.deepEqual(folderPathOf(tree, null), [])
    assert.deepEqual(folderPathOf(tree, 'deleted'), [])
  })
})

describe('fileLocation', () => {
  const virtualFolders = [
    { id: 'virtual:note', source: 'note', name: 'Notes' },
    { id: 'virtual:task', source: 'task', name: 'Tâches' }
  ]

  it('names the folder path and points at the folder holding the file', () => {
    const file = {
      folder_path: [
        { id: 'live', name: 'Live' },
        { id: '2026', name: '2026' }
      ]
    }
    assert.deepEqual(fileLocation(file, virtualFolders), {
      label: 'Live / 2026',
      folderId: '2026',
      songs: []
    })
  })

  it('sends an unfiled attachment to its virtual folder, not to the root that excludes it', () => {
    const file = { folder_path: [], attachments: [{ source_type: 'note', source_id: 'n1' }] }
    assert.deepEqual(fileLocation(file, virtualFolders), {
      label: 'Notes',
      folderId: 'virtual:note',
      songs: []
    })
  })

  it('falls back to the source label when the tree has not loaded yet', () => {
    const file = { attachments: [{ source_type: 'note' }] }
    assert.equal(fileLocation(file, []).label, 'Note')
  })

  it('is the root only for a file that is in no folder and attached to nothing', () => {
    assert.deepEqual(fileLocation({ folder_path: [], attachments: [] }, virtualFolders), {
      label: 'Racine',
      folderId: 'root',
      songs: []
    })
  })

  it('names the song after the folder, « Compo · New 45 »', () => {
    const file = {
      folder_path: [{ id: 'compo', name: 'Compo' }],
      attachments: [{ source_type: 'song', source_id: SONG_ID, source_label: 'New 45' }]
    }
    assert.deepEqual(fileLocation(file, virtualFolders), {
      label: 'Compo',
      folderId: 'compo',
      songs: [{ id: SONG_ID, title: 'New 45' }]
    })
  })

  it('keeps « Chansons » for a file of a song in no folder, then names the song', () => {
    const file = {
      folder_path: [],
      attachments: [{ source_type: 'song', source_id: SONG_ID, source_label: 'New 45' }]
    }
    assert.deepEqual(
      fileLocation(file, [{ id: 'virtual:song', source: 'song', name: 'Chansons' }]),
      {
        label: 'Chansons',
        folderId: 'virtual:song',
        songs: [{ id: SONG_ID, title: 'New 45' }]
      }
    )
  })

  it('names every song a file belongs to, and only songs', () => {
    const file = {
      folder_path: [{ id: 'compo', name: 'Compo' }],
      attachments: [
        { source_type: 'song', source_id: 'ember', source_label: 'Ember Road' },
        { source_type: 'task', source_id: 't1', source_label: 'Envoyer la grille' },
        { source_type: 'song', source_id: SONG_ID, source_label: 'New 45' }
      ]
    }
    assert.deepEqual(fileLocation(file, virtualFolders).songs, [
      { id: 'ember', title: 'Ember Road' },
      { id: SONG_ID, title: 'New 45' }
    ])
  })
})

describe('uploadBelongsInListing', () => {
  const FOLDER = '0198c0de-dead-beef-cafe-000000000001'

  it('holds a file uploaded into the folder on screen', () => {
    assert.equal(uploadBelongsInListing(FOLDER, filters(), FOLDER), true)
    assert.equal(uploadBelongsInListing(ROOT_FOLDER_ID, filters(), null), true)
  })

  it('does not hold a file uploaded somewhere else', () => {
    assert.equal(uploadBelongsInListing(ROOT_FOLDER_ID, filters(), FOLDER), false)
    assert.equal(uploadBelongsInListing(FOLDER, filters(), null), false)
  })

  it('holds anything in the flat listing of the whole space', () => {
    assert.equal(uploadBelongsInListing(null, filters(), FOLDER), true)
  })

  it('holds nothing while the listing is narrowed past a place', () => {
    assert.equal(uploadBelongsInListing(FOLDER, filters({ query: 'contrat' }), FOLDER), false)
    assert.equal(uploadBelongsInListing(FOLDER, filters({ tagId: 'tag-1' }), FOLDER), false)
    assert.equal(uploadBelongsInListing(FOLDER, filters({ mime: 'audio/' }), FOLDER), false)
  })

  it('holds nothing in a virtual folder, which lists attachments rather than uploads', () => {
    assert.equal(uploadBelongsInListing('virtual:note', filters(), null), false)
    assert.equal(uploadBelongsInListing(songFolderId(SONG_ID), filters(), null), false)
    assert.equal(uploadBelongsInListing(TRASH_FOLDER_ID, filters(), null), false)
  })
})

describe('fileVirtualFolders', () => {
  const virtualFolders = [
    { id: 'virtual:note', source: 'note', name: 'Notes' },
    { id: 'virtual:task', source: 'task', name: 'Tâches' }
  ]

  it('is empty for a file nothing is attached to', () => {
    assert.deepEqual(fileVirtualFolders({ attachments: [] }, virtualFolders), [])
    assert.deepEqual(fileVirtualFolders({}, virtualFolders), [])
  })

  it('names one folder per source, however many attachments share it', () => {
    const file = {
      attachments: [
        { source_type: 'note', source_id: 'n1' },
        { source_type: 'task', source_id: 't1' },
        { source_type: 'note', source_id: 'n2' }
      ]
    }
    assert.deepEqual(fileVirtualFolders(file, virtualFolders), [
      { label: 'Notes', folderId: 'virtual:note' },
      { label: 'Tâches', folderId: 'virtual:task' }
    ])
  })

  it('falls back to the source label when the tree has not loaded yet', () => {
    assert.deepEqual(fileVirtualFolders({ attachments: [{ source_type: 'setlist' }] }, []), [
      { label: 'Setlist', folderId: 'virtual:setlist' }
    ])
  })
})

describe('virtualFolderName', () => {
  it('names a source the way the sidebar does', () => {
    const virtualFolders = [{ id: 'virtual:song', source: 'song', name: 'Chansons' }]
    assert.equal(virtualFolderName('song', virtualFolders), 'Chansons')
  })

  it('falls back to the source label when the tree has not loaded yet', () => {
    assert.equal(virtualFolderName('song', []), 'Chanson')
  })
})

describe('canFileSitAtRoot', () => {
  it('holds a file nothing is attached to', () => {
    assert.equal(canFileSitAtRoot({ attachments: [] }), true)
    assert.equal(canFileSitAtRoot({}), true)
  })

  it('refuses an attached file, which the root listing leaves out', () => {
    assert.equal(canFileSitAtRoot({ attachments: [{ source_type: 'note' }] }), false)
  })
})

describe('rootDestinationRefusal', () => {
  const virtualFolders = [
    { id: 'virtual:note', source: 'note', name: 'Notes' },
    { id: 'virtual:task', source: 'task', name: 'Tâches' }
  ]

  it('says nothing for a file the root can hold', () => {
    assert.equal(rootDestinationRefusal({ attachments: [] }, virtualFolders), null)
  })

  it('names the virtual folder listing the file instead of the root', () => {
    const file = { attachments: [{ source_type: 'note', source_id: 'n1' }] }
    assert.equal(
      rootDestinationRefusal(file, virtualFolders),
      'Ce fichier est listé dans Notes : la racine ne liste que les fichiers attachés à aucune ressource.'
    )
  })

  it('names every virtual folder listing it, so the sentence is not half true', () => {
    const file = {
      attachments: [
        { source_type: 'note', source_id: 'n1' },
        { source_type: 'task', source_id: 't1' }
      ]
    }
    assert.equal(
      rootDestinationRefusal(file, virtualFolders),
      'Ce fichier est listé dans Notes et Tâches : la racine ne liste que les fichiers attachés à aucune ressource.'
    )
  })
})

describe('folderSelectOptions', () => {
  const TREE = [
    { id: 'a', name: 'Concerts', children: [{ id: 'a1', name: '2026', children: [] }] },
    { id: 'b', name: 'Maquettes', children: [] }
  ]

  it('puts Racine first and indents by depth', () => {
    assert.deepEqual(folderSelectOptions(TREE), [
      { label: 'Racine', value: null, disabled: false },
      { label: 'Concerts', value: 'a' },
      { label: '— 2026', value: 'a1' },
      { label: 'Maquettes', value: 'b' }
    ])
  })

  it('indents with a hyphen in English', () => {
    i18n.global.locale.value = 'en'
    try {
      assert.equal(folderSelectOptions(TREE)[2].label, '- 2026')
    } finally {
      i18n.global.locale.value = 'fr'
    }
  })

  it('can refuse Racine, which is what an attached file does to it', () => {
    assert.equal(folderSelectOptions(TREE, true)[0].disabled, true)
  })

  it('offers Racine alone for an empty tree', () => {
    assert.deepEqual(folderSelectOptions([]), [{ label: 'Racine', value: null, disabled: false }])
  })
})

describe('activeLocationLabel', () => {
  const tree = [
    { id: 'f1', name: 'Concert Petit Bain', children: [{ id: 'f2', name: 'Photos', children: [] }] }
  ]
  const virtualFolders = [{ source: 'task', name: 'Tâches', file_count: 2 }]

  it('names the root, and a folder by its path from the root', () => {
    assert.equal(activeLocationLabel(ROOT_FOLDER_ID, tree), 'Racine')
    assert.equal(activeLocationLabel('f1', tree), 'Racine › Concert Petit Bain')
    assert.equal(activeLocationLabel('f2', tree), 'Racine › Concert Petit Bain › Photos')
  })

  it('names the selections that are not a place', () => {
    assert.equal(activeLocationLabel(null, tree), 'Tous les fichiers')
    assert.equal(activeLocationLabel(TRASH_FOLDER_ID, tree), 'Corbeille')
    assert.equal(activeLocationLabel('virtual:task', tree, virtualFolders), 'Tâches')
  })

  it('falls back to the root for a folder the tree no longer holds', () => {
    assert.equal(activeLocationLabel('gone', tree), 'Racine')
  })

  it('names a song under « Chansons » after the source', () => {
    const sources = [{ source: 'song', name: 'Chansons', file_count: 4 }]
    const songs = [{ id: SONG_ID, title: 'New 45', fileCount: 3 }]
    assert.equal(
      activeLocationLabel(songFolderId(SONG_ID), tree, sources, songs),
      'Chansons › New 45'
    )
    assert.equal(activeLocationLabel('virtual:song', tree, sources, songs), 'Chansons')
  })

  it('names only the source for a song that has no node any more', () => {
    const sources = [{ source: 'song', name: 'Chansons', file_count: 0 }]
    assert.equal(activeLocationLabel(songFolderId(SONG_ID), tree, sources, []), 'Chansons')
  })
})

describe('findFolder', () => {
  const paris = { id: 'paris', depth: 2, children: [] }
  const tree = [{ id: 'live', depth: 0, children: [{ id: '2026', depth: 1, children: [paris] }] }]

  it('finds a folder at any depth', () => {
    assert.equal(findFolder(tree, 'paris'), paris)
    assert.equal(findFolder(tree, 'live').id, 'live')
  })

  it('finds nothing for a folder that is gone, or for no folder', () => {
    assert.equal(findFolder(tree, 'deleted'), null)
    assert.equal(findFolder(tree, null), null)
  })
})

describe('canHoldSubfolder', () => {
  it('stops one level before the deepest the tree allows', () => {
    assert.equal(canHoldSubfolder({ depth: 0 }), true)
    assert.equal(canHoldSubfolder({ depth: 4 }), true)
    assert.equal(canHoldSubfolder({ depth: 5 }), false)
    assert.equal(canHoldSubfolder({}), true)
  })
})
