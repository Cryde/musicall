import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  firstImageAmong,
  IMAGE_TOO_LARGE_MESSAGE,
  imageRefusalFor,
  MAX_IMAGE_BYTES,
  UNSUPPORTED_IMAGE_MESSAGE
} from './chatImageDraft.js'

/**
 * The client half of ChatMessageCreate's image constraint, so the two cannot drift silently.
 *
 * Run with `npm test`.
 */

describe('imageRefusalFor', () => {
  it('accepts the four formats the server converts or keeps', () => {
    for (const type of ['image/jpeg', 'image/png', 'image/webp', 'image/gif']) {
      assert.equal(imageRefusalFor({ type, size: 1024 }), null)
    }
  })

  it('refuses an SVG, which can carry scripts', () => {
    assert.equal(imageRefusalFor({ type: 'image/svg+xml', size: 1024 }), UNSUPPORTED_IMAGE_MESSAGE)
  })

  it('refuses what is not an image at all', () => {
    assert.equal(
      imageRefusalFor({ type: 'application/pdf', size: 1024 }),
      UNSUPPORTED_IMAGE_MESSAGE
    )
  })

  it('accepts exactly the limit and refuses one byte more', () => {
    assert.equal(imageRefusalFor({ type: 'image/jpeg', size: MAX_IMAGE_BYTES }), null)
    assert.equal(
      imageRefusalFor({ type: 'image/jpeg', size: MAX_IMAGE_BYTES + 1 }),
      IMAGE_TOO_LARGE_MESSAGE
    )
  })
})

describe('firstImageAmong', () => {
  it('picks the first image and ignores the rest', () => {
    const text = { type: 'text/plain' }
    const png = { type: 'image/png' }
    const jpeg = { type: 'image/jpeg' }

    assert.equal(firstImageAmong([text, png, jpeg]), png)
  })

  it('answers null when nothing is an image', () => {
    assert.equal(firstImageAmong([{ type: 'text/plain' }]), null)
    assert.equal(firstImageAmong([]), null)
  })
})
