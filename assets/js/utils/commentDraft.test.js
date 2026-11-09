import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  COMMENT_POST,
  commentAvailability,
  commentDraftPayload,
  readCommentDraft
} from './commentDraft.js'
import { MAX_DRAFT_TEXT_LENGTH } from './draftText.js'
import { keepPendingAction, readPendingAction } from './pendingAction.js'

/**
 * A comment a visitor wrote has to come back after sign-in under the same publication, and has to be dropped with a
 * reason once that publication is gone. Run with `npm test`.
 */

const PUBLICATION = { slug: 'un-super-album', thread: { id: 12 } }

function fakeStorage() {
  const values = {}

  return {
    getItem: (key) => (key in values ? values[key] : null),
    setItem: (key, value) => {
      values[key] = value
    },
    removeItem: (key) => {
      delete values[key]
    }
  }
}

describe('commentDraftPayload', () => {
  it('keeps the thread and the comment, and comes back the same', () => {
    const storage = fakeStorage()
    const payload = commentDraftPayload({ threadId: 12, content: 'Très bon album !' })
    keepPendingAction(
      storage,
      { kind: COMMENT_POST, route: '/publications/un-super-album', payload, stoppedOnSubmit: true },
      { isVisitor: true }
    )

    assert.deepEqual(payload, { threadId: 12, content: 'Très bon album !' })
    assert.deepEqual(readCommentDraft(readPendingAction(storage).payload), payload)
  })

  it('keeps nothing when nothing was typed', () => {
    assert.equal(commentDraftPayload({ threadId: 12, content: '  ' }), null)
  })

  it('keeps nothing when the comment is too long to keep', () => {
    assert.equal(
      commentDraftPayload({ threadId: 12, content: 'a'.repeat(MAX_DRAFT_TEXT_LENGTH + 1) }),
      null
    )
  })
})

describe('readCommentDraft', () => {
  it('refuses a payload that cannot be a comment', () => {
    for (const payload of [
      null,
      'texte',
      { content: 'Bravo' },
      { threadId: '12', content: 'Bravo' },
      { threadId: 0, content: 'Bravo' },
      { threadId: 1.5, content: 'Bravo' },
      { threadId: 12, content: 42 }
    ]) {
      assert.equal(readCommentDraft(payload), null, JSON.stringify(payload))
    }
  })
})

describe('commentAvailability', () => {
  it('is available under the publication it was written for', () => {
    assert.equal(commentAvailability({ publication: PUBLICATION, threadId: 12 }), 'available')
  })

  it('says the publication is gone when it no longer loads, has no thread, or is another one', () => {
    assert.equal(commentAvailability({ publication: null, threadId: 12 }), 'publication_gone')
    assert.equal(
      commentAvailability({ publication: { slug: 'un-super-album' }, threadId: 12 }),
      'publication_gone'
    )
    assert.equal(
      commentAvailability({ publication: { ...PUBLICATION, thread: { id: 13 } }, threadId: 12 }),
      'publication_gone'
    )
  })

  it('does not reopen a draft that could not be read', () => {
    assert.equal(
      commentAvailability({ publication: PUBLICATION, threadId: undefined }),
      'publication_gone'
    )
  })
})
