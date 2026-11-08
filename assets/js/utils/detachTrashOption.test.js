/**
 * These pin the trash option of the detach dialog against the refusals of the detach endpoints'
 * `?archive=true`: BandSpaceTaskFileDetachTest covers the same cases on the server side.
 *
 * Run with `npm test`.
 */

import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { CHAT_MESSAGE_SOURCE_TYPE } from '../constants/fileSources.js'
import { detachTrashOption } from './detachTrashOption.js'

const ALICE = 'user-alice'
const BOB = 'user-bob'
const TASK = { type: 'task', id: 'task-1' }

function file(attachments = [{ source_type: 'task', source_id: 'task-1' }], createdBy = ALICE) {
  return { id: 'file-1', created_by: { id: createdBy }, attachments }
}

describe('detachTrashOption', () => {
  it('offers the trash to the uploader when only this source holds the file', () => {
    assert.deepEqual(detachTrashOption(file(), TASK, ALICE, false), { offered: true, keptBy: [] })
  })

  it('offers it to an admin for a file somebody else uploaded', () => {
    assert.deepEqual(detachTrashOption(file(), TASK, BOB, true), { offered: true, keptBy: [] })
  })

  it('keeps it from a member who neither uploaded the file nor administers the space', () => {
    assert.deepEqual(detachTrashOption(file(), TASK, BOB, false), { offered: false, keptBy: [] })
  })

  // Ownership is the refusal the server reaches first, so the dialog does not explain the other one.
  it('says nothing about other sources to a member who could not trash the file anyway', () => {
    const attachedTwice = file([
      { source_type: 'task', source_id: 'task-1' },
      { source_type: 'note', source_id: 'note-1' }
    ])

    assert.deepEqual(detachTrashOption(attachedTwice, TASK, BOB, false), {
      offered: false,
      keptBy: []
    })
  })

  // A deleted uploader leaves no creator to match, so only an admin can still trash the file.
  for (const createdBy of [null, undefined]) {
    it(`leaves a file with created_by ${createdBy} to admins`, () => {
      const orphan = { ...file(), created_by: createdBy }

      assert.deepEqual(detachTrashOption(orphan, TASK, ALICE, false), {
        offered: false,
        keptBy: []
      })
      assert.deepEqual(detachTrashOption(orphan, TASK, ALICE, true), { offered: true, keptBy: [] })
    })
  }

  // The profile arrives with the session rather than with the file, so it can still be missing.
  it('keeps it away while the current member is not known yet', () => {
    assert.deepEqual(detachTrashOption(file(), TASK, null, false), { offered: false, keptBy: [] })
  })

  it('names the note still holding the file instead of offering the trash', () => {
    const attachedTwice = file([
      { source_type: 'task', source_id: 'task-1' },
      { source_type: 'note', source_id: 'note-1' }
    ])

    assert.deepEqual(detachTrashOption(attachedTwice, TASK, ALICE, false), {
      offered: false,
      keptBy: ['note']
    })
  })

  it('counts another source of the same kind: a second task still holds the file', () => {
    const onTwoTasks = file([
      { source_type: 'task', source_id: 'task-1' },
      { source_type: 'task', source_id: 'task-2' }
    ])

    assert.deepEqual(detachTrashOption(onTwoTasks, TASK, ALICE, false), {
      offered: false,
      keptBy: ['task']
    })
  })

  it('ignores a chat image, which never keeps a file out of the trash', () => {
    const alsoPosted = file([
      { source_type: 'task', source_id: 'task-1' },
      { source_type: CHAT_MESSAGE_SOURCE_TYPE, source_id: 'message-1' }
    ])

    assert.deepEqual(detachTrashOption(alsoPosted, TASK, ALICE, false), {
      offered: true,
      keptBy: []
    })
  })
})
