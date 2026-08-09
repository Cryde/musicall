import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { canDeleteTask, tasksBlockingArchive, tasksBlockingDelete } from './taskActions.js'

/**
 * These pin the rules the board reads off a task before offering a destructive button, so they
 * cannot drift from the endpoints they mirror without a failing test.
 *
 * Run with `npm test`.
 */

const ALICE = 'user-alice'
const BOB = 'user-bob'

function task(overrides = {}) {
  return {
    id: 'task-1',
    title: 'Mix final',
    status: 'todo',
    created_by_id: ALICE,
    archive_datetime: null,
    ...overrides
  }
}

describe('canDeleteTask', () => {
  it('lets the creator delete their own task', () => {
    assert.equal(canDeleteTask(task({ created_by_id: ALICE }), ALICE, false), true)
  })

  it('refuses a member the task they did not create', () => {
    assert.equal(canDeleteTask(task({ created_by_id: ALICE }), BOB, false), false)
  })

  it('lets an administrator delete anybody’s task', () => {
    assert.equal(canDeleteTask(task({ created_by_id: ALICE }), BOB, true), true)
  })

  // The profile arrives with the session rather than with the board, so it can still be missing
  // when the drawer first paints. Nobody is the creator until it lands.
  it('refuses when the current user is not known yet', () => {
    assert.equal(canDeleteTask(task({ created_by_id: ALICE }), null, false), false)
    assert.equal(canDeleteTask(task({ created_by_id: undefined }), null, false), false)
  })
})

describe('tasksBlockingDelete', () => {
  it('names every task of the selection the member did not create', () => {
    const selection = [
      task({ title: 'Ma tâche', created_by_id: BOB }),
      task({ title: 'Mix final', created_by_id: ALICE }),
      task({ title: 'Réserver le studio', created_by_id: ALICE })
    ]

    assert.deepEqual(tasksBlockingDelete(selection, BOB, false), [
      'Mix final',
      'Réserver le studio'
    ])
  })

  it('blocks nothing for an administrator', () => {
    const selection = [task({ created_by_id: ALICE }), task({ created_by_id: BOB })]

    assert.deepEqual(tasksBlockingDelete(selection, BOB, true), [])
  })

  it('blocks nothing when the whole selection is the member’s own', () => {
    assert.deepEqual(tasksBlockingDelete([task({ created_by_id: BOB })], BOB, false), [])
  })

  it('blocks nothing on an empty selection', () => {
    assert.deepEqual(tasksBlockingDelete([], BOB, false), [])
  })
})

describe('tasksBlockingArchive', () => {
  it('names every task of the selection that is not finished', () => {
    const selection = [
      task({ title: 'Mix final', status: 'done' }),
      task({ title: 'Réserver le studio', status: 'todo' }),
      task({ title: 'Écrire le pont', status: 'in_progress' })
    ]

    assert.deepEqual(tasksBlockingArchive(selection), ['Réserver le studio', 'Écrire le pont'])
  })

  // The server leaves an already archived task out of the blocking set, because archiving it a
  // second time is a no-op there rather than a refusal.
  it('leaves out a task that is already archived, whatever its status', () => {
    const selection = [
      task({
        title: 'Master cassette',
        status: 'todo',
        archive_datetime: '2026-04-01T10:00:00+00:00'
      })
    ]

    assert.deepEqual(tasksBlockingArchive(selection), [])
  })

  it('blocks nothing when the whole selection is finished', () => {
    assert.deepEqual(tasksBlockingArchive([task({ status: 'done' })]), [])
  })

  it('blocks nothing on an empty selection', () => {
    assert.deepEqual(tasksBlockingArchive([]), [])
  })
})
