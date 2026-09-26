import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { routeLiveSignal } from './liveSignalRouter.js'

/** Which handlers a signal reaches, in order, with what. Run with `npm test`. */
function route(payload) {
  const calls = []
  const record =
    (name) =>
    (...args) =>
      calls.push([name, ...args])
  routeLiveSignal(payload, {
    refreshBell: record('refreshBell'),
    refreshNotificationCounts: record('refreshNotificationCounts'),
    inboxMessage: record('inboxMessage'),
    chatMessage: record('chatMessage'),
    chatRead: record('chatRead'),
    chatMessageChanged: record('chatMessageChanged')
  })

  return calls
}

describe('routeLiveSignal', () => {
  it('sends a chat read to the chat read handler and nowhere else', () => {
    // Nowhere else is the third rule against the cascade: no badge refresh, no inbox refetch, and
    // above all not the message handler, which marks the conversation read and would publish again.
    assert.deepEqual(route({ type: 'band_space_chat_read', band_space_id: 'space-1' }), [
      ['chatRead', 'space-1']
    ])
  })

  it('sends a changed message to its own handler and nowhere else', () => {
    // No badge refresh and no message handler: a reaction is not new content, and the message
    // handler would mark the conversation read.
    assert.deepEqual(
      route({
        type: 'band_space_message_changed',
        band_space_id: 'space-1',
        thread_id: 'thread-1',
        message_id: 'message-1',
        change: 'reaction'
      }),
      [
        [
          'chatMessageChanged',
          { bandSpaceId: 'space-1', messageId: 'message-1', change: 'reaction' }
        ]
      ]
    )
  })

  it('drops a changed message that names no space or no message', () => {
    assert.deepEqual(route({ type: 'band_space_message_changed', message_id: 'message-1' }), [])
    assert.deepEqual(route({ type: 'band_space_message_changed', band_space_id: 'space-1' }), [])
  })

  it('drops a chat read that names no space', () => {
    assert.deepEqual(route({ type: 'band_space_chat_read' }), [])
  })

  it('refreshes everything on a reconnect, exactly as before, without the chat read handler', () => {
    assert.deepEqual(route(null), [
      ['refreshBell'],
      ['refreshNotificationCounts'],
      ['inboxMessage', null],
      ['chatMessage', null],
      ['inboxMessage', null]
    ])
  })

  it('treats a payload with no type as a reconnect', () => {
    assert.deepEqual(route({}), route(null))
  })

  it('routes a notification to the bell only', () => {
    assert.deepEqual(route({ type: 'notification' }), [['refreshBell']])
  })

  it('routes a direct message to the counts and the inbox', () => {
    assert.deepEqual(route({ type: 'message', thread_id: 'thread-1' }), [
      ['refreshNotificationCounts'],
      ['inboxMessage', 'thread-1']
    ])
  })

  it('routes a channel message to the chat and the inbox', () => {
    assert.deepEqual(
      route({ type: 'band_space_message', band_space_id: 'space-1', thread_id: 'thread-1' }),
      [
        ['chatMessage', 'space-1'],
        ['inboxMessage', 'thread-1']
      ]
    )
  })

  it('ignores a type it does not know', () => {
    assert.deepEqual(route({ type: 'something_new' }), [])
  })
})
