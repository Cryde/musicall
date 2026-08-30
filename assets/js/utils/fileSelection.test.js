/**
 * These pin the click arithmetic the file list reads before it acts on a selection, so a range or an
 * anchor cannot drift without a failing test. Trashing the wrong photos is the failure mode.
 *
 * Run with `npm test`.
 */

import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  areAllSelected,
  prunedSelection,
  rangeBetween,
  selectionAfterClick
} from './fileSelection.js'

const ROWS = ['a', 'b', 'c', 'd', 'e']

describe('rangeBetween', () => {
  it('reads downwards', () => {
    assert.deepEqual(rangeBetween(ROWS, 'b', 'd'), ['b', 'c', 'd'])
  })

  it('reads upwards too, so shift-clicking above the anchor works', () => {
    assert.deepEqual(rangeBetween(ROWS, 'd', 'b'), ['b', 'c', 'd'])
  })

  it('is inclusive of a single row', () => {
    assert.deepEqual(rangeBetween(ROWS, 'c', 'c'), ['c'])
  })

  it('spans the whole list', () => {
    assert.deepEqual(rangeBetween(ROWS, 'a', 'e'), ROWS)
  })

  it('selects nothing when a row is not on screen', () => {
    assert.deepEqual(rangeBetween(ROWS, 'a', 'zz'), [])
    assert.deepEqual(rangeBetween(ROWS, 'zz', 'a'), [])
  })
})

describe('selectionAfterClick', () => {
  it('adds a row and takes the anchor with it', () => {
    assert.deepEqual(
      selectionAfterClick({ id: 'b', orderedIds: ROWS, selected: [], anchorId: null }),
      { selected: ['b'], anchorId: 'b' }
    )
  })

  it('removes a row that was already selected', () => {
    assert.deepEqual(
      selectionAfterClick({ id: 'b', orderedIds: ROWS, selected: ['a', 'b'], anchorId: 'a' }),
      { selected: ['a'], anchorId: 'b' }
    )
  })

  it('selects the run between the anchor and the row, leaving the anchor where it was', () => {
    assert.deepEqual(
      selectionAfterClick({
        id: 'd',
        orderedIds: ROWS,
        selected: ['b'],
        anchorId: 'b',
        shift: true
      }),
      { selected: ['b', 'c', 'd'], anchorId: 'b' }
    )
  })

  // The anchor staying put is what lets a member widen and narrow one run by shift-clicking around.
  it('redraws the run rather than growing it when shift-clicking back towards the anchor', () => {
    const wide = selectionAfterClick({
      id: 'e',
      orderedIds: ROWS,
      selected: ['b'],
      anchorId: 'b',
      shift: true
    })
    assert.deepEqual(wide.selected, ['b', 'c', 'd', 'e'])

    const narrow = selectionAfterClick({
      id: 'c',
      orderedIds: ROWS,
      selected: wide.selected,
      anchorId: wide.anchorId,
      shift: true
    })
    assert.deepEqual(narrow, { selected: ['b', 'c'], anchorId: 'b' })
  })

  it('falls back to a toggle when shift is held with no anchor yet', () => {
    assert.deepEqual(
      selectionAfterClick({ id: 'c', orderedIds: ROWS, selected: [], anchorId: null, shift: true }),
      { selected: ['c'], anchorId: 'c' }
    )
  })

  // A member holding shift always means to select something, so an anchor that has scrolled out of
  // the loaded rows must not select nothing at all.
  it('falls back to a toggle when the anchor is no longer on screen', () => {
    assert.deepEqual(
      selectionAfterClick({
        id: 'c',
        orderedIds: ROWS,
        selected: [],
        anchorId: 'gone',
        shift: true
      }),
      { selected: ['c'], anchorId: 'c' }
    )
  })
})

describe('prunedSelection', () => {
  it('drops ids the list no longer holds', () => {
    assert.deepEqual(prunedSelection(['a', 'zz', 'c'], ROWS), ['a', 'c'])
  })

  it('keeps a selection that is entirely on screen', () => {
    assert.deepEqual(prunedSelection(['a', 'c'], ROWS), ['a', 'c'])
  })

  it('empties a selection when the list does', () => {
    assert.deepEqual(prunedSelection(['a', 'c'], []), [])
  })
})

describe('areAllSelected', () => {
  it('is true only when every row on screen is ticked', () => {
    assert.equal(areAllSelected(ROWS, ROWS), true)
    assert.equal(areAllSelected(ROWS, ['a', 'b']), false)
  })

  it('ignores selected ids that are not on screen', () => {
    assert.equal(areAllSelected(['a', 'b'], ['a', 'b', 'zz']), true)
  })

  // An empty list has nothing to tick, so the header checkbox must not read as ticked.
  it('is false for an empty list', () => {
    assert.equal(areAllSelected([], []), false)
  })
})
