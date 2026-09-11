import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { isMessageAlreadyListed, messageSignalPlan } from './messageSignal.js'

describe('messageSignalPlan', () => {
  it('does nothing when the inbox was never opened', () => {
    assert.deepEqual(
      messageSignalPlan({
        inboxLoaded: false,
        signalThreadId: 'a-thread',
        openThreadId: 'a-thread',
        tabVisible: true
      }),
      { refreshInbox: false, refreshOpenThread: false, markRead: false }
    )
  })

  it('refreshes the list but not the messages when the signal is for another thread', () => {
    assert.deepEqual(
      messageSignalPlan({
        inboxLoaded: true,
        signalThreadId: 'another-thread',
        openThreadId: 'the-open-one',
        tabVisible: true
      }),
      { refreshInbox: true, refreshOpenThread: false, markRead: false }
    )
  })

  it('refreshes the messages when the signal is for the thread on screen', () => {
    assert.deepEqual(
      messageSignalPlan({
        inboxLoaded: true,
        signalThreadId: 'the-open-one',
        openThreadId: 'the-open-one',
        tabVisible: true
      }),
      { refreshInbox: true, refreshOpenThread: true, markRead: true }
    )
  })

  it('leaves a message unread when the tab is in the background', () => {
    // The thread is open, but nobody is looking at it. Marking it read here is the difference
    // between an unread count that means something and one that does not.
    assert.deepEqual(
      messageSignalPlan({
        inboxLoaded: true,
        signalThreadId: 'the-open-one',
        openThreadId: 'the-open-one',
        tabVisible: false
      }),
      { refreshInbox: true, refreshOpenThread: true, markRead: false }
    )
  })

  it('refreshes the open thread too when a reconnect leaves us without a thread id', () => {
    // A recovered connection carries no payload, so we know something was missed but not what.
    // Refreshing the list and leaving the conversation on screen stale is the worst of both.
    assert.deepEqual(
      messageSignalPlan({
        inboxLoaded: true,
        signalThreadId: null,
        openThreadId: 'the-open-one',
        tabVisible: true
      }),
      { refreshInbox: true, refreshOpenThread: true, markRead: true }
    )
  })

  it('refreshes only the list on a reconnect when no conversation is open', () => {
    assert.deepEqual(
      messageSignalPlan({
        inboxLoaded: true,
        signalThreadId: null,
        openThreadId: null,
        tabVisible: true
      }),
      { refreshInbox: true, refreshOpenThread: false, markRead: false }
    )
  })

  it('lights up a first ever conversation, where the list loaded and was empty', () => {
    // `threads.length > 0` would have read this as "never opened" and done nothing, so somebody's
    // very first message would not have appeared.
    assert.deepEqual(
      messageSignalPlan({
        inboxLoaded: true,
        signalThreadId: 'a-brand-new-thread',
        openThreadId: null,
        tabVisible: true
      }),
      { refreshInbox: true, refreshOpenThread: false, markRead: false }
    )
  })
})

describe('isMessageAlreadyListed', () => {
  const listed = [{ '@id': '/api/messages/one' }, { '@id': '/api/messages/two' }]

  it('recognises a message the refetch already added', () => {
    assert.equal(isMessageAlreadyListed(listed, { '@id': '/api/messages/two' }), true)
  })

  it('does not recognise one that is genuinely new', () => {
    assert.equal(isMessageAlreadyListed(listed, { '@id': '/api/messages/three' }), false)
  })

  it('treats an empty conversation as having nothing in it', () => {
    assert.equal(isMessageAlreadyListed([], { '@id': '/api/messages/one' }), false)
  })

  it('treats a message with no identifier as new rather than as a duplicate', () => {
    // Matching on two undefined identifiers would swallow every message after the first. Showing one
    // twice is recoverable on the next refetch; dropping somebody's message is not.
    assert.equal(isMessageAlreadyListed([{}], {}), false)
  })
})
