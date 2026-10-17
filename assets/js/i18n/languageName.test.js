import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { languageName } from './languageName.js'

describe('languageName', () => {
  it('names each language in itself, capitalised as a label', () => {
    assert.equal(languageName('fr'), 'Français')
    assert.equal(languageName('en'), 'English')
    assert.equal(languageName('de'), 'Deutsch')
  })
})
