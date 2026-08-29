import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { canDrop, collectFolderAndDescendants, directChildren } from './useFolderDragDrop.js'

/**
 * Drag and drop is the one move surface with no dialog to explain itself, so what it refuses has to be
 * refused before the drop rather than undone after it. The root is the case worth pinning: it lists the
 * files nothing is attached to, so dropping an attached file there would take it out of the tree
 * instead of moving it, exactly what the move dialog used to allow.
 *
 * Run with `npm test`.
 */

const TREE = [
  {
    id: 'live',
    name: 'Live',
    children: [{ id: '2026', name: '2026', children: [{ id: 'paris', name: 'Paris' }] }]
  },
  { id: 'riders', name: 'Riders' }
]

describe('collectFolderAndDescendants', () => {
  it('takes the folder and everything under it', () => {
    assert.deepEqual(collectFolderAndDescendants(TREE, 'live'), ['live', '2026', 'paris'])
  })

  it('is just the folder when it has no children', () => {
    assert.deepEqual(collectFolderAndDescendants(TREE, 'riders'), ['riders'])
  })
})

describe('directChildren', () => {
  it('is the roots at the top and the direct children below', () => {
    assert.deepEqual(directChildren(TREE, null), TREE)
    assert.deepEqual(directChildren(TREE, '2026'), [{ id: 'paris', name: 'Paris' }])
  })

  it('is empty for a leaf and for a folder the tree does not hold', () => {
    assert.deepEqual(directChildren(TREE, 'riders'), [])
    assert.deepEqual(directChildren(TREE, 'deleted'), [])
  })
})

describe('canDrop', () => {
  const folder = (overrides = {}) => ({
    type: 'folder',
    id: 'live',
    parentId: null,
    descendantIds: ['live', '2026', 'paris'],
    ...overrides
  })

  const file = (overrides = {}) => ({
    type: 'file',
    id: 'f1',
    folderId: 'live',
    attachments: [],
    ...overrides
  })

  it('refuses a drop with nothing being dragged, and an unknown source', () => {
    assert.equal(canDrop(null, 'riders'), false)
    assert.equal(canDrop({ type: 'tag', id: 't1' }, 'riders'), false)
  })

  it('refuses a folder on itself or on a descendant, which would orphan the subtree', () => {
    assert.equal(canDrop(folder(), 'live'), false)
    assert.equal(canDrop(folder(), 'paris'), false)
  })

  it('refuses a folder on the parent it already hangs on', () => {
    assert.equal(canDrop(folder(), null), false)
    assert.equal(canDrop(folder({ parentId: 'riders' }), 'riders'), false)
  })

  it('accepts a folder anywhere else, the root included', () => {
    assert.equal(canDrop(folder(), 'riders'), true)
    assert.equal(canDrop(folder({ parentId: 'riders' }), null), true)
  })

  it('refuses a file on the folder it is already in', () => {
    assert.equal(canDrop(file(), 'live'), false)
    assert.equal(canDrop(file({ folderId: null }), null), false)
  })

  it('accepts a file into another folder, attached or not', () => {
    assert.equal(canDrop(file(), 'riders'), true)
    assert.equal(canDrop(file({ attachments: [{ source_type: 'note' }] }), 'riders'), true)
  })

  it('accepts an unattached file on the root, which lists it', () => {
    assert.equal(canDrop(file(), null), true)
  })

  it('refuses an attached file on the root, which would take it out of the tree', () => {
    assert.equal(canDrop(file({ attachments: [{ source_type: 'note' }] }), null), false)
  })
})
