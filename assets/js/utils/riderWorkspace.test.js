import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  aggregateSaveStatus,
  movedIds,
  neighbourAfterRemoval,
  selectableItemId
} from './riderWorkspace.js'

/**
 * The tech rider workspace shows one section at a time and one status for the whole rider (#1091).
 * These pin the status a user reads and the section they land on after each action.
 *
 * Run with `npm test`.
 */

describe('aggregateSaveStatus', () => {
  it('reads as saved when nothing was edited yet', () => {
    assert.equal(aggregateSaveStatus([]), 'saved')
  })

  it('reads as saving while anything is not confirmed', () => {
    assert.equal(aggregateSaveStatus(['saved', 'pending']), 'saving')
    assert.equal(aggregateSaveStatus(['saving', 'saved']), 'saving')
  })

  it('puts an error first, whatever else is going on', () => {
    assert.equal(aggregateSaveStatus(['saving', 'error', 'saved']), 'error')
  })

  it('reads as saved once every section is', () => {
    assert.equal(aggregateSaveStatus(['saved', 'saved']), 'saved')
  })
})

describe('movedIds', () => {
  it('moves a section one step', () => {
    assert.deepEqual(movedIds(['a', 'b', 'c'], 'b', -1), ['b', 'a', 'c'])
    assert.deepEqual(movedIds(['a', 'b', 'c'], 'b', 1), ['a', 'c', 'b'])
  })

  it('refuses a move off either end', () => {
    assert.equal(movedIds(['a', 'b'], 'a', -1), null)
    assert.equal(movedIds(['a', 'b'], 'b', 1), null)
  })

  it('refuses a section that is not in the list', () => {
    assert.equal(movedIds(['a', 'b'], 'z', 1), null)
  })
})

describe('selectableItemId', () => {
  it('keeps the section asked for', () => {
    assert.equal(selectableItemId(['a', 'b'], 'b'), 'b')
  })

  it('falls back to the first section for an unknown or missing id', () => {
    assert.equal(selectableItemId(['a', 'b'], 'gone'), 'a')
    assert.equal(selectableItemId(['a', 'b'], undefined), 'a')
  })

  it('selects nothing in a rider without sections', () => {
    assert.equal(selectableItemId([], 'a'), null)
  })
})

describe('neighbourAfterRemoval', () => {
  it('moves to the next section', () => {
    assert.equal(neighbourAfterRemoval(['a', 'b', 'c'], 'b'), 'c')
  })

  it('moves to the previous one when the last is removed', () => {
    assert.equal(neighbourAfterRemoval(['a', 'b', 'c'], 'c'), 'b')
  })

  it('selects nothing once the only section is gone', () => {
    assert.equal(neighbourAfterRemoval(['a'], 'a'), null)
  })
})
