import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { MAX_PROFILE_MEDIA, mediaAddTargets, ownerCardContent } from './profileOwner.js'

const media = (count) => Array.from({ length: count }, (_, index) => ({ id: `m${index}` }))

describe('ownerCardContent', () => {
  it('shows the items whoever looks', () => {
    assert.equal(ownerCardContent([{ id: 1 }], false), 'items')
    assert.equal(ownerCardContent([{ id: 1 }], true), 'items')
  })

  it('shows an empty card as a hint to the owner who can fill it in', () => {
    assert.equal(ownerCardContent([], true), 'hint')
  })

  it('shows a visitor nothing for an empty card', () => {
    assert.equal(ownerCardContent([], false), null)
    assert.equal(ownerCardContent(undefined, false), null)
  })
})

describe('mediaAddTargets', () => {
  it('offers both profiles to a member who has both', () => {
    assert.deepEqual(mediaAddTargets({ musician: { media: [] }, teacher: { media: media(2) } }), [
      'musician',
      'teacher'
    ])
  })

  it('offers only the profile the member has', () => {
    assert.deepEqual(mediaAddTargets({ musician: null, teacher: { media: [] } }), ['teacher'])
    assert.deepEqual(mediaAddTargets({ musician: { media: [] }, teacher: null }), ['musician'])
  })

  it('leaves out a profile already holding its maximum', () => {
    assert.deepEqual(
      mediaAddTargets({ musician: { media: media(MAX_PROFILE_MEDIA) }, teacher: { media: [] } }),
      ['teacher']
    )
  })

  it('offers nothing to a member with no musician or teacher profile', () => {
    assert.deepEqual(mediaAddTargets({ musician: null, teacher: null }), [])
    assert.deepEqual(mediaAddTargets(null), [])
  })
})
