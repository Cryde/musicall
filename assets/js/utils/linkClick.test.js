import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { followsInPlace, followsLinkInPlace } from './linkClick.js'

const plainClick = { metaKey: false, altKey: false, ctrlKey: false, shiftKey: false, button: 0 }

/** A click whose target sits in `link`, or in no link at all when it is null. */
function clickIn(link, modifiers = {}) {
  return { ...plainClick, ...modifiers, target: { closest: () => link } }
}

function linkTo(target = null) {
  return { getAttribute: (name) => (name === 'target' ? target : null) }
}

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

describe('followsLinkInPlace', () => {
  it('closes on a link followed in this tab, from the link or anything inside it', () => {
    assert.equal(followsLinkInPlace(clickIn(linkTo())), true)
  })

  it('stays open for a click outside any link', () => {
    assert.equal(followsLinkInPlace(clickIn(null)), false)
  })

  it('stays open for a link opened elsewhere', () => {
    assert.equal(followsLinkInPlace(clickIn(linkTo(), { ctrlKey: true })), false)
    assert.equal(followsLinkInPlace(clickIn(linkTo('_blank'))), false)
  })

  it('stays open for a target that is not an element', () => {
    assert.equal(followsLinkInPlace({ ...plainClick, target: null }), false)
  })
})
