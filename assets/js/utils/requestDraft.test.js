import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  clearRequestDrafts,
  DRAFT_LIFETIME_MS,
  hasRequestDraft,
  saveRequestDraft,
  takeRequestDraft
} from './requestDraft.js'

function memoryStorage() {
  const items = new Map()
  return {
    get length() {
      return items.size
    },
    key: (index) => [...items.keys()][index] ?? null,
    getItem: (key) => (items.has(key) ? items.get(key) : null),
    setItem: (key, value) => items.set(key, String(value)),
    removeItem: (key) => items.delete(key),
    items
  }
}

describe('request draft', () => {
  it('gives the answers back once, for the same key whatever the case', () => {
    const storage = memoryStorage()
    saveRequestDraft(
      storage,
      'Julien',
      { answers: { level: 'beginner' }, message: 'Bonjour' },
      1000
    )
    assert.equal(hasRequestDraft(storage, 'julien', 2000), true)
    assert.deepEqual(takeRequestDraft(storage, 'julien', 2000), {
      answers: { level: 'beginner' },
      message: 'Bonjour'
    })
    assert.equal(takeRequestDraft(storage, 'julien', 2000), null)
    assert.equal(hasRequestDraft(storage, 'julien', 2000), false)
  })

  it('forgets a draft older than its lifetime, and keeps keys apart', () => {
    const storage = memoryStorage()
    saveRequestDraft(storage, 'julien', { answers: {}, message: null }, 0)
    assert.equal(takeRequestDraft(storage, 'claire', 10), null)
    assert.equal(hasRequestDraft(storage, 'julien', DRAFT_LIFETIME_MS + 1), false)
    assert.equal(takeRequestDraft(storage, 'julien', DRAFT_LIFETIME_MS + 1), null)
  })

  it('survives a blocked or broken storage', () => {
    const blocked = {
      getItem: () => {
        throw new Error('blocked')
      },
      setItem: () => {
        throw new Error('blocked')
      },
      removeItem: () => {}
    }
    saveRequestDraft(blocked, 'julien', { answers: {} })
    assert.equal(takeRequestDraft(blocked, 'julien'), null)
    assert.equal(hasRequestDraft(blocked, 'julien'), false)
    assert.equal(takeRequestDraft(null, 'julien'), null)

    const broken = memoryStorage()
    broken.setItem('request-draft:julien', '{not json')
    assert.equal(takeRequestDraft(broken, 'julien'), null)
  })

  it('forgets every request draft at logout, and nothing else', () => {
    const storage = memoryStorage()
    saveRequestDraft(storage, 'julien', { answers: {} })
    saveRequestDraft(storage, 'claire', { answers: {} })
    storage.setItem('something-else', 'kept')
    clearRequestDrafts(storage)
    assert.deepEqual([...storage.items.keys()], ['something-else'])
    clearRequestDrafts(null)
  })
})
