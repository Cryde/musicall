import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { canDeleteChatMessage } from './chatMessageActions.js'

/**
 * These pin the rule the chat pane reads off a message before offering a bin, so it cannot drift
 * from ChatMessageDeleteProcessor without a failing test.
 *
 * Run with `npm test`.
 */

function message(overrides = {}) {
  return {
    id: 'message-1',
    author_id: 'user-alice',
    content: 'on r\u00e9p\u00e8te mardi',
    is_deleted: false,
    ...overrides
  }
}

describe('canDeleteChatMessage', () => {
  it('lets the author delete their own message', () => {
    assert.equal(canDeleteChatMessage(message(), true, false), true)
  })

  it('refuses a plain member somebody else\u2019s message', () => {
    assert.equal(canDeleteChatMessage(message(), false, false), false)
  })

  it('lets an administrator delete somebody else\u2019s message', () => {
    assert.equal(canDeleteChatMessage(message(), false, true), true)
  })

  it('refuses an already deleted message, to the author and to an administrator alike', () => {
    const tombstone = message({ content: '', is_deleted: true })
    assert.equal(canDeleteChatMessage(tombstone, true, false), false)
    assert.equal(canDeleteChatMessage(tombstone, false, true), false)
  })

  it('refuses a missing message instead of throwing', () => {
    assert.equal(canDeleteChatMessage(null, true, true), false)
    assert.equal(canDeleteChatMessage(undefined, true, true), false)
  })
})
