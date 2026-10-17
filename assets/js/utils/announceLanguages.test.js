import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  ANNOUNCE_LANGUAGES,
  isAnnounceLanguage,
  languageLabel,
  languageListLabel,
  languageName,
  languageOptions
} from './announceLanguages.js'

describe('announce languages', () => {
  it('names a language as a sentence writes it, in the interface language', () => {
    assert.equal(languageName('en'), 'anglais')
    assert.equal(languageName('nl', 'en'), 'Dutch')
  })

  it('capitalises a language standing alone as a label', () => {
    assert.equal(languageLabel('fr'), 'Français')
    assert.equal(languageLabel('de', 'en'), 'German')
  })

  it('lists the languages of an announce as one label', () => {
    assert.equal(languageListLabel(['fr']), 'Français')
    assert.equal(languageListLabel(['fr', 'en']), 'Français et anglais')
    assert.equal(languageListLabel(['nl', 'de', 'en'], 'en-GB'), 'Dutch, German and English')
  })

  it('lists no language as nothing', () => {
    assert.equal(languageListLabel([]), '')
  })

  it('offers every language an announce can have, in their order', () => {
    assert.deepEqual(languageOptions(), [
      { code: 'fr', name: 'Français' },
      { code: 'en', name: 'Anglais' },
      { code: 'nl', name: 'Néerlandais' },
      { code: 'de', name: 'Allemand' }
    ])
    assert.deepEqual(
      languageOptions('en').map((option) => option.code),
      ANNOUNCE_LANGUAGES
    )
  })

  it('accepts only the languages an announce can have', () => {
    assert.equal(isAnnounceLanguage('nl'), true)
    assert.equal(isAnnounceLanguage('es'), false)
    assert.equal(isAnnounceLanguage('EN'), false)
    assert.equal(isAnnounceLanguage(''), false)
    assert.equal(isAnnounceLanguage(undefined), false)
  })
})
