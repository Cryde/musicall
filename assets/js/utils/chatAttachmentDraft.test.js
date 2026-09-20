import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  addToDraft,
  canSendDraft,
  DUPLICATE_ATTACHMENT_MESSAGE,
  draftAfterSend,
  draftIdentifiers,
  FULL_DRAFT_MESSAGE,
  MAX_CHAT_ATTACHMENTS,
  removeFromDraft,
  UNUSABLE_ATTACHMENT_MESSAGE
} from './chatAttachmentDraft.js'

/**
 * These pin the rules the composer applies before a reference reaches the server, so the cap and the
 * duplicate refusal cannot drift from ChatMessageCreate without a failing test.
 *
 * Run with `npm test`.
 */

function searchResult(type, resourceId, title = `${type} ${resourceId}`) {
  return {
    id: `${type}-${resourceId}`,
    type,
    resource_id: resourceId,
    title,
    subtitle: null
  }
}

/** A draft of `count` distinct tasks, which is the quickest way to a full one. */
function draftOf(count) {
  let attachments = []
  for (let index = 0; index < count; index += 1) {
    attachments = addToDraft(attachments, searchResult('task', `task-${index}`)).attachments
  }

  return attachments
}

describe('addToDraft', () => {
  it('adds a picked result, keeping only what the chip and the wire need', () => {
    const { attachments, error } = addToDraft(
      [],
      searchResult('agenda', 'abc', 'Concert au Botanique')
    )

    assert.equal(error, null)
    assert.deepEqual(attachments, [
      { id: 'agenda-abc', type: 'agenda', title: 'Concert au Botanique' }
    ])
  })

  it('appends rather than replacing, so several objects ride on one message', () => {
    const first = addToDraft([], searchResult('task', 'one')).attachments
    const { attachments } = addToDraft(first, searchResult('note', 'two'))

    assert.deepEqual(draftIdentifiers(attachments), ['task-one', 'note-two'])
  })

  it('never mutates the draft it was handed', () => {
    const before = addToDraft([], searchResult('task', 'one')).attachments
    addToDraft(before, searchResult('note', 'two'))

    assert.deepEqual(draftIdentifiers(before), ['task-one'])
  })

  it('refuses the same object twice and says so', () => {
    const before = addToDraft([], searchResult('file', 'contrat')).attachments
    const { attachments, error } = addToDraft(before, searchResult('file', 'contrat'))

    assert.equal(error, DUPLICATE_ATTACHMENT_MESSAGE)
    assert.deepEqual(draftIdentifiers(attachments), ['file-contrat'])
  })

  it('tells apart two kinds of record sharing one id, which the synthetic identifier is for', () => {
    const before = addToDraft([], searchResult('setlist', 'same')).attachments
    const { attachments, error } = addToDraft(before, searchResult('song', 'same'))

    assert.equal(error, null)
    assert.deepEqual(draftIdentifiers(attachments), ['setlist-same', 'song-same'])
  })

  it('refuses one past the cap and says so, rather than dropping it silently', () => {
    const full = draftOf(MAX_CHAT_ATTACHMENTS)
    const { attachments, error } = addToDraft(full, searchResult('note', 'sixth'))

    assert.equal(error, FULL_DRAFT_MESSAGE)
    assert.equal(attachments.length, MAX_CHAT_ATTACHMENTS)
    assert.equal(
      attachments.some((attachment) => attachment.id === 'note-sixth'),
      false
    )
  })

  it('answers a full draft re-offered its own object as a duplicate, the more precise reason', () => {
    const full = draftOf(MAX_CHAT_ATTACHMENTS)
    const { error } = addToDraft(full, searchResult('task', 'task-0'))

    assert.equal(error, DUPLICATE_ATTACHMENT_MESSAGE)
  })

  it('refuses a candidate with no identifier instead of putting undefined on the wire', () => {
    for (const candidate of [null, undefined, {}, { type: 'task', title: 'sans id' }]) {
      const { attachments, error } = addToDraft([], candidate)
      assert.equal(error, UNUSABLE_ATTACHMENT_MESSAGE)
      assert.deepEqual(attachments, [])
    }
  })

  it('accepts a result whose title is missing rather than refusing it, since the server snapshots its own label', () => {
    const { attachments, error } = addToDraft([], { id: 'task-x', type: 'task' })

    assert.equal(error, null)
    assert.deepEqual(attachments, [{ id: 'task-x', type: 'task', title: '' }])
  })
})

describe('removeFromDraft', () => {
  it('takes out the one asked for and leaves the rest in order', () => {
    const draft = draftOf(3)

    assert.deepEqual(draftIdentifiers(removeFromDraft(draft, 'task-task-1')), [
      'task-task-0',
      'task-task-2'
    ])
  })

  it('frees a slot, so an object can be swapped for another on a full message', () => {
    const full = draftOf(MAX_CHAT_ATTACHMENTS)
    const freed = removeFromDraft(full, 'task-task-0')
    const { attachments, error } = addToDraft(freed, searchResult('note', 'replacement'))

    assert.equal(error, null)
    assert.equal(attachments.length, MAX_CHAT_ATTACHMENTS)
  })

  it('leaves the draft alone for an id it does not hold', () => {
    const draft = draftOf(2)

    assert.deepEqual(draftIdentifiers(removeFromDraft(draft, 'note-absent')), [
      'task-task-0',
      'task-task-1'
    ])
  })
})

describe('draftAfterSend', () => {
  it('empties the draft once the message is out, the way the composer empties the text', () => {
    assert.deepEqual(draftAfterSend(draftOf(2), true), [])
  })

  it('keeps every reference when the send failed, so the retry is one click', () => {
    const draft = draftOf(2)

    assert.deepEqual(draftAfterSend(draft, false), draft)
  })
})

describe('draftIdentifiers', () => {
  it('sends the synthetic identifiers through untranslated, which is what the endpoint accepts', () => {
    const draft = addToDraft([], searchResult('finance', 'loyer')).attachments

    assert.deepEqual(draftIdentifiers(draft), ['finance-loyer'])
  })

  it('answers an empty draft with an empty list rather than nothing', () => {
    assert.deepEqual(draftIdentifiers([]), [])
  })
})

describe('canSendDraft', () => {
  it('refuses a message with neither text nor attachment, as the server does', () => {
    assert.equal(canSendDraft('', []), false)
  })

  it('refuses whitespace alone, since there is still nothing to read', () => {
    assert.equal(canSendDraft('  \n ', []), false)
  })

  it('sends text on its own', () => {
    assert.equal(canSendDraft('salut', []), true)
  })

  it('sends an attachment with no text at all', () => {
    assert.equal(canSendDraft('', draftOf(1)), true)
  })

  it('sends an attachment beside whitespace, which the server stores as no text', () => {
    assert.equal(canSendDraft('   ', draftOf(1)), true)
  })
})
