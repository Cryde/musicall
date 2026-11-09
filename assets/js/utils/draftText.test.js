import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { isKeepableText, MAX_DRAFT_TEXT_LENGTH } from './draftText.js'

/** What a visitor typed is kept over sign-in only while it is text of a reasonable size. Run with `npm test`. */

describe('isKeepableText', () => {
  it('keeps text, an empty one included', () => {
    assert.equal(isKeepableText('Salut tout le monde'), true)
    assert.equal(isKeepableText(''), true)
  })

  it('keeps text up to the limit, not past it', () => {
    assert.equal(isKeepableText('a'.repeat(MAX_DRAFT_TEXT_LENGTH)), true)
    assert.equal(isKeepableText('a'.repeat(MAX_DRAFT_TEXT_LENGTH + 1)), false)
  })

  it('refuses anything that is not text', () => {
    for (const value of [null, undefined, 42, ['texte'], { text: 'texte' }]) {
      assert.equal(isKeepableText(value), false, JSON.stringify(value))
    }
  })
})
