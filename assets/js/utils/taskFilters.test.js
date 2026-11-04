import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { sheetFilterCount } from './taskFilters.js'

const NONE = {
  categoryId: null,
  assigneeId: null,
  unassigned: false,
  priority: null,
  myTasks: false,
  overdue: false,
  showArchived: false,
  query: '',
  dueDateFrom: null,
  dueDateTo: null
}

describe('sheetFilterCount', () => {
  it('counts nothing on a fresh board', () => {
    assert.equal(sheetFilterCount(NONE), 0)
  })

  it('counts each filter the sheet holds', () => {
    assert.equal(
      sheetFilterCount({
        ...NONE,
        categoryId: 'c1',
        assigneeId: 'u1',
        unassigned: true,
        priority: 'high',
        overdue: true,
        showArchived: true
      }),
      6
    )
  })

  it('leaves out « Mes tâches » and the search, which sit outside the sheet', () => {
    assert.equal(sheetFilterCount({ ...NONE, myTasks: true, query: 'camion' }), 0)
  })

  it('counts a due date range once, with one end or both', () => {
    assert.equal(sheetFilterCount({ ...NONE, dueDateFrom: '2026-11-01' }), 1)
    assert.equal(sheetFilterCount({ ...NONE, dueDateTo: '2026-11-30' }), 1)
    assert.equal(
      sheetFilterCount({ ...NONE, dueDateFrom: '2026-11-01', dueDateTo: '2026-11-30' }),
      1
    )
  })
})
