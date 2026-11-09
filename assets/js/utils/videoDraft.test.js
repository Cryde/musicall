import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { keepPendingAction, readPendingAction } from './pendingAction.js'
import {
  COURSE_CREATE,
  courseDraftPayload,
  discoverDraftPayload,
  PUBLICATION_DISCOVER,
  readCourseDraft,
  readDiscoverDraft
} from './videoDraft.js'

/**
 * The link of a discovery or a course a visitor started has to come back after sign-in, with the course's category
 * while it still exists. Run with `npm test`.
 */

const VIDEO_URL = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
const CATEGORIES = [
  { id: 1, slug: 'guitare', title: 'Guitare' },
  { id: 2, slug: 'basse', title: 'Basse' }
]

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

function throughStorage(kind, payload) {
  const storage = fakeStorage()
  keepPendingAction(
    storage,
    { kind, route: '/publications', payload, stoppedOnSubmit: true },
    { isVisitor: true }
  )

  return readPendingAction(storage).payload
}

describe('discoverDraftPayload', () => {
  it('keeps the link, and comes back the same', () => {
    const payload = discoverDraftPayload({ url: VIDEO_URL })

    assert.deepEqual(payload, { url: VIDEO_URL })
    assert.deepEqual(readDiscoverDraft(throughStorage(PUBLICATION_DISCOVER, payload)), {
      url: VIDEO_URL
    })
  })

  it('keeps nothing when the link is too long to be one', () => {
    assert.equal(discoverDraftPayload({ url: `${VIDEO_URL}&${'a'.repeat(2048)}` }), null)
  })
})

describe('readDiscoverDraft', () => {
  it('gives back an empty link when the stored one is unusable', () => {
    for (const payload of [null, 'texte', {}, { url: 42 }, { url: 'a'.repeat(2049) }]) {
      assert.deepEqual(readDiscoverDraft(payload), { url: '' }, JSON.stringify(payload))
    }
  })
})

describe('courseDraftPayload', () => {
  it('keeps the link and the category id, and comes back with the category', () => {
    const payload = courseDraftPayload({ url: VIDEO_URL, categoryId: 2 })

    assert.deepEqual(payload, { url: VIDEO_URL, categoryId: 2 })
    assert.deepEqual(readCourseDraft(throughStorage(COURSE_CREATE, payload), CATEGORIES), {
      url: VIDEO_URL,
      category: CATEGORIES[1]
    })
  })

  it('keeps a link without a category yet', () => {
    assert.deepEqual(courseDraftPayload({ url: VIDEO_URL, categoryId: undefined }), {
      url: VIDEO_URL,
      categoryId: null
    })
  })

  it('keeps nothing when the link is too long to be one', () => {
    assert.equal(courseDraftPayload({ url: 'a'.repeat(2049), categoryId: 1 }), null)
  })
})

describe('readCourseDraft', () => {
  it('leaves out a category gone from the list, so the form asks for it again', () => {
    assert.deepEqual(readCourseDraft({ url: VIDEO_URL, categoryId: 9 }, CATEGORIES), {
      url: VIDEO_URL,
      category: null
    })
  })

  it('gives back an empty form for a payload that cannot be one', () => {
    for (const payload of [null, [], { url: null, categoryId: '1' }]) {
      assert.deepEqual(
        readCourseDraft(payload, CATEGORIES),
        { url: '', category: null },
        JSON.stringify(payload)
      )
    }
  })
})
