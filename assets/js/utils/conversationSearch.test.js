import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { filterConversations } from './conversationSearch.js'

/**
 * The inbox's search, on the device. Run with `npm test`.
 */

function conversation(title, { channel = null, preview = null } = {}) {
  return {
    title,
    thread: { channel_name: channel, last_message: preview ? { content_preview: preview } : null }
  }
}

const lea = conversation('Léa Martin', { preview: 'Tu es dispo samedi pour une répète ?' })
const band = conversation('The Collaborators', {
  channel: 'Général',
  preview: 'rex46 : Je prends la camionnette'
})
const empty = conversation('Maxime Durand')
const all = [lea, band, empty]
const titleOf = (threadMeta) => threadMeta.title

describe('filterConversations', () => {
  it('keeps every conversation for an empty search', () => {
    assert.deepEqual(filterConversations(all, '', titleOf), all)
    assert.deepEqual(filterConversations(all, '   ', titleOf), all)
  })

  it('finds a person whatever the case and the accents', () => {
    assert.deepEqual(filterConversations(all, 'lea', titleOf), [lea])
    assert.deepEqual(filterConversations(all, 'MARTIN', titleOf), [lea])
  })

  it('finds a band by its name or its channel', () => {
    assert.deepEqual(filterConversations(all, 'collab', titleOf), [band])
    assert.deepEqual(filterConversations(all, 'general', titleOf), [band])
  })

  it('finds a conversation by its last message', () => {
    assert.deepEqual(filterConversations(all, 'repete', titleOf), [lea])
  })

  it('keeps nothing when nothing matches, a conversation with no message included', () => {
    assert.deepEqual(filterConversations(all, 'batterie', titleOf), [])
  })
})
