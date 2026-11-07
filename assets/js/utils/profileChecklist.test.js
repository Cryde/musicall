import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { profileChecklist } from './profileChecklist.js'

const keys = (checklist) => checklist.items.map((item) => item.key)

describe('profileChecklist', () => {
  it('asks a member with no musician or teacher profile for photos, a bio and a post', () => {
    const checklist = profileChecklist({ has_posts: false })

    assert.deepEqual(keys(checklist), ['photos', 'bio', 'post'])
    assert.equal(checklist.percent, 0)
  })

  it('adds the instruments and the media for a musician', () => {
    assert.deepEqual(keys(profileChecklist({ musician: { instruments: [], media: [] } })), [
      'photos',
      'bio',
      'instruments',
      'media',
      'post'
    ])
  })

  it('adds the media and the availability for a teacher', () => {
    assert.deepEqual(keys(profileChecklist({ teacher: { media: [], availability: [] } })), [
      'photos',
      'bio',
      'media',
      'post',
      'availability'
    ])
  })

  it('reads the six items of the mock-up for a musician who teaches', () => {
    const checklist = profileChecklist({
      profile_picture_url: '/p.jpg',
      cover_picture_url: '/c.jpg',
      bio: 'Guitariste',
      has_posts: false,
      musician: { instruments: [{ instrument_id: 'g' }], media: [] },
      teacher: { media: [{ id: 'm' }], availability: [] }
    })

    assert.deepEqual(checklist.items, [
      { key: 'photos', done: true },
      { key: 'bio', done: true },
      { key: 'instruments', done: true },
      { key: 'media', done: true },
      { key: 'post', done: false },
      { key: 'availability', done: false }
    ])
    assert.equal(checklist.percent, 67)
  })

  it('wants both the photo and the cover, and a bio that is not blank', () => {
    const checklist = profileChecklist({ profile_picture_url: '/p.jpg', bio: '   ' })

    assert.deepEqual(checklist.items.slice(0, 2), [
      { key: 'photos', done: false },
      { key: 'bio', done: false }
    ])
  })

  it('is complete when every item is done', () => {
    const checklist = profileChecklist({
      profile_picture_url: '/p.jpg',
      cover_picture_url: '/c.jpg',
      bio: 'Guitariste',
      has_posts: true
    })

    assert.equal(checklist.percent, 100)
  })
})
