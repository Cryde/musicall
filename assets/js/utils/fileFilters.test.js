import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  fileFilterChipLabels,
  fileSortOptions,
  fileSortValue,
  fileTypeOptions,
  parseFileSort
} from './fileFilters.js'

const DEFAULTS = { query: '', tagId: null, mime: null, sort: 'date', order: 'desc' }
const TAGS = [
  { id: 't1', name: 'Concert' },
  { id: 't2', name: 'Démo' }
]

describe('file filter options', () => {
  it('offers the four types and the six orders', () => {
    assert.deepEqual(
      fileTypeOptions().map((option) => option.value),
      ['audio/', 'image/', 'application/pdf', 'video/']
    )
    assert.deepEqual(
      fileSortOptions().map((option) => option.value),
      ['date:desc', 'date:asc', 'name:asc', 'name:desc', 'size:desc', 'size:asc']
    )
  })

  it('reads a sort option back into its two filters', () => {
    assert.equal(fileSortValue(DEFAULTS), 'date:desc')
    assert.deepEqual(parseFileSort('size:asc'), { sort: 'size', order: 'asc' })
    assert.equal(parseFileSort(null), null)
    assert.equal(parseFileSort('size'), null)
  })
})

describe('fileFilterChipLabels', () => {
  it('names each filter while nothing is chosen, and the order in use', () => {
    assert.deepEqual(fileFilterChipLabels(DEFAULTS, TAGS), {
      tag: 'Tag',
      type: 'Type',
      sort: 'Plus récents'
    })
  })

  it('says the choice once one is made', () => {
    assert.deepEqual(
      fileFilterChipLabels(
        { ...DEFAULTS, tagId: 't2', mime: 'application/pdf', sort: 'name', order: 'asc' },
        TAGS
      ),
      { tag: 'Démo', type: 'PDF', sort: 'Nom (A→Z)' }
    )
  })

  it('falls back to the filter name for a tag that is gone', () => {
    assert.equal(fileFilterChipLabels({ ...DEFAULTS, tagId: 'deleted' }, TAGS).tag, 'Tag')
  })
})
