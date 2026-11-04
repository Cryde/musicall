import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { followsInPlace } from './linkClick.js'

const plainClick = { metaKey: false, altKey: false, ctrlKey: false, shiftKey: false, button: 0 }

describe('followsInPlace', () => {
  it('follows a plain click in this tab', () => {
    assert.equal(followsInPlace(plainClick), true)
  })

  it('follows a click from the keyboard, which names no button', () => {
    assert.equal(followsInPlace({ ...plainClick, button: undefined }), true)
  })

  for (const key of ['metaKey', 'ctrlKey', 'shiftKey', 'altKey']) {
    it(`leaves the page alone when ${key} is held, as the link opens elsewhere`, () => {
      assert.equal(followsInPlace({ ...plainClick, [key]: true }), false)
    })
  }

  it('leaves the page alone for another mouse button', () => {
    assert.equal(followsInPlace({ ...plainClick, button: 1 }), false)
  })

  it('leaves the page alone for a link to a new tab', () => {
    assert.equal(followsInPlace(plainClick, '_blank'), false)
  })

  it('follows a link that names another target than a new tab', () => {
    assert.equal(followsInPlace(plainClick, '_self'), true)
  })
})
