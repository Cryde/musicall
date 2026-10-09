import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { canDeleteNote } from './noteActions.js'

/** Who is offered « Supprimer » on a note (#1166). Run with `npm test`. */
const note = { created_by: { id: 'author' } }

describe('canDeleteNote', () => {
  it('lets the author delete their note', () => {
    assert.equal(canDeleteNote(note, 'author', false), true)
  })

  it('lets an administrator delete anybody', () => {
    assert.equal(canDeleteNote(note, 'someone-else', true), true)
  })

  it('refuses any other member', () => {
    assert.equal(canDeleteNote(note, 'someone-else', false), false)
  })

  it('refuses while the reader is unknown, rather than matching a note with no author', () => {
    assert.equal(canDeleteNote({}, null, false), false)
    assert.equal(canDeleteNote({ created_by: { id: undefined } }, undefined, false), false)
  })
})
