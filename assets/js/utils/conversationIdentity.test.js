import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { conversationTitle, isChannel } from './conversationIdentity.js'

const directMessage = (participants = []) => ({
  thread: { id: 'thread-1', message_participants: participants }
})

const channel = (overrides = {}) => ({
  thread: {
    id: 'thread-2',
    band_space_id: 'space-1',
    band_space_name: 'Les Copains',
    channel_name: 'Général',
    ...overrides
  }
})

describe('isChannel', () => {
  it('recognises a channel by its band space', () => {
    assert.equal(isChannel(channel()), true)
  })

  it('reads a direct message as no channel', () => {
    assert.equal(isChannel(directMessage()), false)
  })

  it('reads a thread whose only other party closed their account as a direct message', () => {
    // The distinguishing fact is the band space, not the empty participant list: both are empty here.
    assert.equal(isChannel(directMessage()), false)
  })

  it('does not choke on a row that is not loaded yet', () => {
    assert.equal(isChannel(undefined), false)
    assert.equal(isChannel({}), false)
  })
})

describe('conversationTitle', () => {
  it('names a channel after its band', () => {
    assert.equal(conversationTitle(channel()), 'Les Copains')
  })

  it('names a direct message after the other party', () => {
    assert.equal(conversationTitle(directMessage(), { username: 'user_base' }), 'user_base')
  })

  it('says so when the other party closed their account', () => {
    assert.equal(
      conversationTitle(directMessage(), {
        username: 'user_base',
        deletion_datetime: '2026-09-01'
      }),
      'Utilisateur supprimé'
    )
  })

  it('falls back rather than rendering nothing when there is no participant at all', () => {
    assert.equal(conversationTitle(directMessage(), null), 'Utilisateur inconnu')
  })

  it('falls back for a channel whose band name did not travel', () => {
    assert.equal(conversationTitle(channel({ band_space_name: null })), 'Groupe')
  })
})
