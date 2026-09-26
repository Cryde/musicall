import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { isHeld, mayMergeNewestPage, paneAfterWindow, withRefreshedHeld } from './chatWindow.js'

/**
 * Run with `npm test`.
 */

describe('paneAfterWindow', () => {
  it('stays a window while the thread goes on past it', () => {
    assert.deepEqual(paneAfterWindow({ has_older: true, has_newer: true, total_items: 190 }, 51), {
      isAtTail: false,
      hasOlder: true,
      hasNewer: true,
      total: null
    })
  })

  it('says so when nothing older is left above the window', () => {
    assert.equal(
      paneAfterWindow({ has_older: false, has_newer: true, total_items: 190 }, 28).hasOlder,
      false
    )
  })

  it('becomes the tail again once it reaches the newest message', () => {
    assert.deepEqual(
      paneAfterWindow({ has_older: true, has_newer: false, total_items: 193 }, 179),
      {
        isAtTail: true,
        hasOlder: false,
        hasNewer: false,
        total: 193
      }
    )
  })

  it('never reports a total below what is held, a message written meanwhile included', () => {
    assert.equal(
      paneAfterWindow({ has_older: true, has_newer: false, total_items: 40 }, 42).total,
      42
    )
  })
})

describe('mayMergeNewestPage', () => {
  it('merges a load the member asked for, wherever the pane is', () => {
    assert.equal(mayMergeNewestPage({ silent: false, isAtTail: true }), true)
    assert.equal(mayMergeNewestPage({ silent: false, isAtTail: false }), true)
  })

  it('merges a live refetch into the newest messages', () => {
    assert.equal(mayMergeNewestPage({ silent: true, isAtTail: true }), true)
  })

  it('never splices the newest page into a window, a jump that started meanwhile included', () => {
    assert.equal(mayMergeNewestPage({ silent: true, isAtTail: false }), false)
  })
})

describe('isHeld', () => {
  it('tells a message on screen from one that needs a window', () => {
    const held = [{ id: 'a' }, { id: 'b' }]

    assert.equal(isHeld(held, 'b'), true)
    assert.equal(isHeld(held, 'z'), false)
    assert.equal(isHeld([], 'a'), false)
  })
})

describe('withRefreshedHeld', () => {
  const message = (id, fields = {}) => ({
    '@id': `/messages/${id}`,
    id,
    content: id,
    reactions: [],
    ...fields
  })

  it('takes the fetched copy of a held message and keeps its place', () => {
    const held = [message('a'), message('b'), message('c')]
    const fetched = [
      message('b', { content: 'modifié', reactions: [{ emoji: 'heart', count: 1 }] })
    ]

    assert.deepEqual(withRefreshedHeld(held, fetched, new Set()), [held[0], fetched[0], held[2]])
  })

  it('never adds a message the pane does not hold', () => {
    const held = [message('a')]

    assert.deepEqual(withRefreshedHeld(held, [message('a'), message('z')], new Set()), [
      message('a')
    ])
  })

  it('keeps the reactions on screen while a tap on that message is in flight', () => {
    const tapped = message('a', { reactions: [{ emoji: 'heart', count: 2, reacted: true }] })
    const stale = message('a', {
      content: 'modifié',
      reactions: [{ emoji: 'heart', count: 1, reacted: false }]
    })

    assert.deepEqual(withRefreshedHeld([tapped], [stale], new Set(['a'])), [
      { ...stale, reactions: tapped.reactions }
    ])
  })

  it('turns a deleted message into its tombstone', () => {
    const tombstone = message('a', { content: '', is_deleted: true })

    assert.deepEqual(withRefreshedHeld([message('a')], [tombstone], new Set()), [tombstone])
  })
})
