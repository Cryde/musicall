import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { NO_FOLDER_LISTED, ROOT_FOLDER_ID, TRASH_FOLDER_ID } from '../constants/folderSelection.js'
import {
  fileListingParams,
  fileLocation,
  folderFileCountLabel,
  folderPathOf,
  isSearchActive,
  listedFolderOfRows,
  treeHoldsFolder,
  uploadBelongsInListing
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
    assert.deepEqual(fileLocation(file, virtualFolders), { label: 'Live / 2026', folderId: '2026' })
  })

  it('sends an unfiled attachment to its virtual folder, not to the root that excludes it', () => {
    const file = { folder_path: [], attachments: [{ source_type: 'note', source_id: 'n1' }] }
    assert.deepEqual(fileLocation(file, virtualFolders), {
      label: 'Notes',
      folderId: 'virtual:note'
    })
  })

  it('falls back to the source label when the tree has not loaded yet', () => {
    const file = { attachments: [{ source_type: 'note' }] }
    assert.equal(fileLocation(file, []).label, 'Note')
  })

  it('is the root only for a file that is in no folder and attached to nothing', () => {
    assert.deepEqual(fileLocation({ folder_path: [], attachments: [] }, virtualFolders), {
      label: 'Racine',
      folderId: 'root'
    })
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
    assert.equal(uploadBelongsInListing(TRASH_FOLDER_ID, filters(), null), false)
  })
})
