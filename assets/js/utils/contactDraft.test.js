import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { contactAvailability, contactDraftPayload, readContactDraft } from './contactDraft.js'
import { keepPendingAction, readPendingAction } from './pendingAction.js'

/**
 * A message a visitor wrote from a search card has to come back after sign-in to the same member about the same
 * announce, and has to be dropped with a reason when that member can no longer be written to. Run with `npm test`.
 */

const ANNOUNCE_ID = 'announce-1'
const AUTHOR = { id: 'user-jeanne', username: 'jeanne', display_name: 'Jeanne' }
const ANNOUNCE = { id: ANNOUNCE_ID, author: AUTHOR }

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

describe('contactDraftPayload', () => {
  it('keeps the announce, the recipient and the message, nothing of their profile', () => {
    assert.deepEqual(
      contactDraftPayload({
        announceId: ANNOUNCE_ID,
        recipientId: 'user-jeanne',
        content: 'Salut !'
      }),
      { announceId: ANNOUNCE_ID, recipientId: 'user-jeanne', content: 'Salut !' }
    )
  })

  it('comes back the same through a pending action', () => {
    const storage = fakeStorage()
    const payload = contactDraftPayload({
      announceId: ANNOUNCE_ID,
      recipientId: 'user-jeanne',
      content: 'Salut, toujours dispo ?'
    })
    keepPendingAction(
      storage,
      { kind: 'message.send', route: '/rechercher-un-musicien', payload, stoppedOnSubmit: true },
      { isVisitor: true }
    )

    assert.deepEqual(readContactDraft(readPendingAction(storage).payload), payload)
  })
})

describe('readContactDraft', () => {
  it('refuses a payload that does not name both the announce and the recipient', () => {
    assert.equal(readContactDraft(null), null)
    assert.equal(readContactDraft('Salut'), null)
    assert.equal(
      readContactDraft({ announceId: null, recipientId: 'user-jeanne', content: 'Salut' }),
      null
    )
    assert.equal(
      readContactDraft({ announceId: ANNOUNCE_ID, recipientId: 7, content: 'Salut' }),
      null
    )
    assert.equal(
      readContactDraft({ announceId: ANNOUNCE_ID, recipientId: 'user-jeanne', content: ['Salut'] }),
      null
    )
  })
})

describe('contactAvailability', () => {
  it('lets the message go to the author of the announce', () => {
    assert.equal(
      contactAvailability({
        announce: ANNOUNCE,
        recipientId: 'user-jeanne',
        signedInUsername: 'paul'
      }),
      'available'
    )
  })

  it('says the announce is gone when it no longer exists', () => {
    assert.equal(
      contactAvailability({ announce: null, recipientId: 'user-jeanne', signedInUsername: 'paul' }),
      'announce_gone'
    )
  })

  it('says the announce is gone when it no longer belongs to the recipient', () => {
    assert.equal(
      contactAvailability({
        announce: ANNOUNCE,
        recipientId: 'user-other',
        signedInUsername: 'paul'
      }),
      'announce_gone'
    )
  })

  it('says the recipient deleted their account', () => {
    const announce = {
      ...ANNOUNCE,
      author: { ...AUTHOR, deletion_datetime: '2026-11-01T10:00:00+00:00' }
    }

    assert.equal(
      contactAvailability({ announce, recipientId: 'user-jeanne', signedInUsername: 'paul' }),
      'recipient_deleted'
    )
  })

  it('says it is the member who signed in that wrote the announce', () => {
    assert.equal(
      contactAvailability({
        announce: ANNOUNCE,
        recipientId: 'user-jeanne',
        signedInUsername: 'jeanne'
      }),
      'own_announce'
    )
  })
})
