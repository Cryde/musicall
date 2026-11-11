import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { hasOlderToLoad, mergeMessages, nextOlderPageToLoad } from './messagePagination.js'

const at = (iri, time) => ({ '@id': iri, creation_datetime: time })

describe('nextOlderPageToLoad', () => {
  it('asks for the second page once a full first page is held', () => {
    assert.equal(nextOlderPageToLoad(50), 2)
  })

  it('still asks for the second page after live messages grew the list past 50', () => {
    // The three that arrived sit at the head, so the rows already held have slid to offsets 3 to 52
    // and page 2 starts three rows inside them. Repeats, which the merge drops; never a hole.
    assert.equal(nextOlderPageToLoad(53), 2)
  })

  it('moves on once the second page has been merged', () => {
    assert.equal(nextOlderPageToLoad(100), 3)
  })

  it('asks for the first page when nothing is held', () => {
    assert.equal(nextOlderPageToLoad(0), 1)
  })

  it('does not divide by a page size of zero', () => {
    assert.equal(nextOlderPageToLoad(50, 0), 1)
  })
})

describe('hasOlderToLoad', () => {
  it('is true while the server reports more than is held', () => {
    assert.equal(hasOlderToLoad(50, 120), true)
  })

  it('is false once everything is held', () => {
    assert.equal(hasOlderToLoad(120, 120), false)
  })

  it('is false when the list somehow holds more than reported', () => {
    // A live message arriving between the count and the render, which must not offer a button that
    // would fetch a page the server does not have.
    assert.equal(hasOlderToLoad(121, 120), false)
  })
})

describe('mergeMessages', () => {
  it('puts an older page in front of what is held', () => {
    const held = [at('/api/messages/c', '2026-09-11T10:00:02+00:00')]
    const older = [
      at('/api/messages/a', '2026-09-11T10:00:00+00:00'),
      at('/api/messages/b', '2026-09-11T10:00:01+00:00')
    ]

    assert.deepEqual(
      mergeMessages(held, older).map((m) => m['@id']),
      ['/api/messages/a', '/api/messages/b', '/api/messages/c']
    )
  })

  it('drops a row the page repeats', () => {
    // The whole reason pages are merged rather than concatenated.
    const held = [
      at('/api/messages/b', '2026-09-11T10:00:01+00:00'),
      at('/api/messages/c', '2026-09-11T10:00:02+00:00')
    ]
    const page = [
      at('/api/messages/a', '2026-09-11T10:00:00+00:00'),
      at('/api/messages/b', '2026-09-11T10:00:01+00:00')
    ]

    assert.deepEqual(
      mergeMessages(held, page).map((m) => m['@id']),
      ['/api/messages/a', '/api/messages/b', '/api/messages/c']
    )
  })

  it('appends what a live refetch of the first page brings', () => {
    const held = [
      at('/api/messages/a', '2026-09-11T10:00:00+00:00'),
      at('/api/messages/b', '2026-09-11T10:00:01+00:00')
    ]
    const firstPageAgain = [
      at('/api/messages/b', '2026-09-11T10:00:01+00:00'),
      at('/api/messages/c', '2026-09-11T10:00:03+00:00')
    ]

    assert.deepEqual(
      mergeMessages(held, firstPageAgain).map((m) => m['@id']),
      ['/api/messages/a', '/api/messages/b', '/api/messages/c']
    )
  })

  it('keeps both messages written in the same second', () => {
    // The column is second granular, so ties are ordinary rather than rare. A merge that deduplicated
    // on the timestamp would silently eat one of them.
    const held = [at('/api/messages/b', '2026-09-11T10:00:00+00:00')]
    const page = [at('/api/messages/a', '2026-09-11T10:00:00+00:00')]

    assert.deepEqual(
      mergeMessages(held, page).map((m) => m['@id']),
      ['/api/messages/b', '/api/messages/a']
    )
  })

  it('takes the fetched copy of a message it already held', () => {
    const held = [
      { '@id': '/api/messages/a', creation_datetime: '2026-09-11T10:00:00+00:00', content: 'old' }
    ]
    const page = [
      { '@id': '/api/messages/a', creation_datetime: '2026-09-11T10:00:00+00:00', content: 'new' }
    ]

    assert.deepEqual(mergeMessages(held, page), [
      { '@id': '/api/messages/a', creation_datetime: '2026-09-11T10:00:00+00:00', content: 'new' }
    ])
  })

  it('keeps a message with no identifier rather than dropping it', () => {
    const page = [{ creation_datetime: '2026-09-11T10:00:00+00:00' }]

    assert.equal(mergeMessages([], page).length, 1)
  })

  it('sorts a message with no usable timestamp oldest rather than losing track of it', () => {
    // Nothing should produce one, and if something does it must not jump to the end of a conversation
    // it does not belong at. Oldest is the position that cannot be mistaken for the newest message.
    const held = [at('/api/messages/b', '2026-09-11T10:00:01+00:00')]
    const broken = [{ '@id': '/api/messages/a', creation_datetime: 'not a date' }]

    assert.deepEqual(
      mergeMessages(held, broken).map((m) => m['@id']),
      ['/api/messages/a', '/api/messages/b']
    )
  })

  it('keeps every message when neither input is actually sorted', () => {
    // The order may be wrong, which is a display problem; losing one would not be.
    const held = [
      at('/api/messages/c', '2026-09-11T10:00:02+00:00'),
      at('/api/messages/a', '2026-09-11T10:00:00+00:00')
    ]
    const incoming = [
      at('/api/messages/d', '2026-09-11T10:00:03+00:00'),
      at('/api/messages/b', '2026-09-11T10:00:01+00:00')
    ]

    assert.deepEqual(
      mergeMessages(held, incoming)
        .map((m) => m['@id'])
        .sort(),
      ['/api/messages/a', '/api/messages/b', '/api/messages/c', '/api/messages/d']
    )
  })

  it('never mutates either input', () => {
    const held = [at('/api/messages/b', '2026-09-11T10:00:01+00:00')]
    const page = [at('/api/messages/a', '2026-09-11T10:00:00+00:00')]
    mergeMessages(held, page)

    assert.equal(held.length, 1)
    assert.equal(page.length, 1)
  })
})
