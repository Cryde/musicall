import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  contactAvailability,
  contactDraftPayload,
  profileContactAvailability,
  readContactDraft
} from './contactDraft.js'
import { keepPendingAction, readPendingAction } from './pendingAction.js'

/**
 * A message a visitor wrote from a search card or a profile has to come back after sign-in to the same member about
 * the same announce, and has to be dropped with a reason when that member can no longer be written to. Run with
 * `npm test`.
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

  it('keeps no announce for a message written on a profile', () => {
    assert.deepEqual(contactDraftPayload({ recipientId: 'user-jeanne', content: 'Salut !' }), {
      announceId: null,
      recipientId: 'user-jeanne',
      content: 'Salut !'
    })
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
  it('reads back a message about an announce', () => {
    assert.deepEqual(
      readContactDraft({ announceId: ANNOUNCE_ID, recipientId: 'user-jeanne', content: 'Salut' }),
      { announceId: ANNOUNCE_ID, recipientId: 'user-jeanne', content: 'Salut' }
    )
  })

  it('reads back a message about no announce, as written on a profile', () => {
    assert.deepEqual(
      readContactDraft({ announceId: null, recipientId: 'user-jeanne', content: 'Salut' }),
      { announceId: null, recipientId: 'user-jeanne', content: 'Salut' }
    )
  })

  it('leaves out anything else the payload carries', () => {
    assert.deepEqual(
      readContactDraft({
        announceId: null,
        recipientId: 'user-jeanne',
        content: 'Salut',
        email: 'jeanne@example.com'
      }),
      { announceId: null, recipientId: 'user-jeanne', content: 'Salut' }
    )
  })

  it('refuses a payload that does not name the recipient, or names the announce wrongly', () => {
    assert.equal(readContactDraft(null), null)
    assert.equal(readContactDraft('Salut'), null)
    assert.equal(readContactDraft({ recipientId: 'user-jeanne', content: 'Salut' }), null)
    assert.equal(
      readContactDraft({ announceId: 12, recipientId: 'user-jeanne', content: 'Salut' }),
      null
    )
    assert.equal(
      readContactDraft({ announceId: ANNOUNCE_ID, recipientId: 7, content: 'Salut' }),
      null
    )
    assert.equal(readContactDraft({ announceId: null, recipientId: null, content: 'Salut' }), null)
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

describe('profileContactAvailability', () => {
  const PROFILE = {
    user_id: 'user-jeanne',
    username: 'jeanne',
    display_name: 'Jeanne',
    musician_announces: [{ id: ANNOUNCE_ID }]
  }

  function availability(facts) {
    return profileContactAvailability({
      profile: PROFILE,
      recipientId: 'user-jeanne',
      announceId: null,
      signedInUsername: 'paul',
      ...facts
    })
  }

  it('lets the message go to the member of the profile', () => {
    assert.equal(availability({}), 'available')
  })

  it('lets the message go about an announce the profile still lists', () => {
    assert.equal(availability({ announceId: ANNOUNCE_ID }), 'available')
  })

  it('lets the message go from a teacher profile, which lists no announce', () => {
    assert.equal(
      availability({
        profile: { user_id: 'user-jeanne', username: 'jeanne', display_name: 'Jeanne' }
      }),
      'available'
    )
  })

  it('says the profile is gone when it could not be loaded', () => {
    assert.equal(availability({ profile: null }), 'profile_gone')
  })

  it('says the profile is gone when it is no longer the recipient', () => {
    assert.equal(availability({ recipientId: 'user-other' }), 'profile_gone')
  })

  it('says the recipient deleted their account', () => {
    assert.equal(
      availability({ profile: { ...PROFILE, deletion_datetime: '2026-11-01T10:00:00+00:00' } }),
      'recipient_deleted'
    )
  })

  it('says it is the profile of the member who signed in', () => {
    assert.equal(availability({ signedInUsername: 'jeanne' }), 'own_profile')
  })

  it('says the announce is gone when the profile no longer lists it', () => {
    assert.equal(availability({ announceId: 'announce-2' }), 'announce_gone')
    assert.equal(
      availability({
        announceId: ANNOUNCE_ID,
        profile: { user_id: 'user-jeanne', username: 'jeanne' }
      }),
      'announce_gone'
    )
  })
})
