import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  failedSaveNoteIds,
  holdsUnsavedContent,
  noteSaveStatus,
  withNoteSaveStatus,
  withoutNoteSaveStatus
} from './noteSaveStatus.js'

/**
 * The bug these pin: one status for the whole module, written by whichever save answered last. A
 * save fired by a timer lands when it lands, so a failure on the note being left painted an error
 * badge on the note being opened, and the note that actually failed said nothing.
 *
 * Run with `npm test`.
 */

describe('noteSaveStatus', () => {
  it('answers only for the note that was saved', () => {
    const statuses = withNoteSaveStatus({}, 'note-a', 'error')

    assert.equal(noteSaveStatus(statuses, 'note-a'), 'error')
    assert.equal(noteSaveStatus(statuses, 'note-b'), null, 'the untouched note stays untouched')
  })

  it('answers null when no note is open', () => {
    assert.equal(noteSaveStatus({ 'note-a': 'saved' }, null), null)
  })

  it('replaces the status of one note and leaves the others alone', () => {
    const statuses = withNoteSaveStatus(
      withNoteSaveStatus({}, 'note-a', 'saving'),
      'note-b',
      'error'
    )

    assert.deepEqual(withNoteSaveStatus(statuses, 'note-a', 'saved'), {
      'note-a': 'saved',
      'note-b': 'error'
    })
  })

  /** Reopening a note fetches a fresh copy, so whatever its last save answered no longer holds. */
  it('drops one note without disturbing the rest', () => {
    const statuses = { 'note-a': 'conflict', 'note-b': 'error' }

    assert.deepEqual(withoutNoteSaveStatus(statuses, 'note-a'), { 'note-b': 'error' })
  })

  it('drops a note it does not hold without complaining', () => {
    assert.deepEqual(withoutNoteSaveStatus({ 'note-a': 'saved' }, 'note-b'), { 'note-a': 'saved' })
  })
})

describe('failedSaveNoteIds, asked before leaving the module and before opening another note', () => {
  it('lists a refused save and a failed one', () => {
    const statuses = { 'note-a': 'conflict', 'note-b': 'error', 'note-c': 'saved' }

    assert.deepEqual(failedSaveNoteIds(statuses).sort(), ['note-a', 'note-b'])
  })

  /**
   * Both exits unmount the editor, which flushes what was waiting, so a save in flight is on its
   * way to the server. Asking about it would be asking about nothing, and a confirm popped every
   * time somebody opens another note within two seconds of typing is a confirm nobody reads.
   */
  it('leaves out a save still in flight', () => {
    assert.deepEqual(failedSaveNoteIds({ 'note-a': 'saving' }), [])
  })

  it('is empty for a module where everything saved', () => {
    assert.deepEqual(failedSaveNoteIds({ 'note-a': 'saved', 'note-b': 'saved' }), [])
  })
})

describe('holdsUnsavedContent, which is what the tab is closed on', () => {
  it('counts a save still in flight, because closing the tab kills it', () => {
    assert.equal(holdsUnsavedContent({ 'note-a': 'saving' }), true)
  })

  it('counts a failed and a refused save', () => {
    assert.equal(holdsUnsavedContent({ 'note-a': 'error' }), true)
    assert.equal(holdsUnsavedContent({ 'note-a': 'conflict' }), true)
  })

  it('is false once everything has been written', () => {
    assert.equal(holdsUnsavedContent({ 'note-a': 'saved', 'note-b': 'saved' }), false)
    assert.equal(holdsUnsavedContent({}), false)
  })
})
