import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  languageOptions,
  offersLanguageChoice,
  translationLanguagesLeft
} from './publicationLanguage.js'

describe('offersLanguageChoice', () => {
  it('offers a choice only when more than one locale is enabled', () => {
    assert.equal(offersLanguageChoice(['fr']), false)
    assert.equal(offersLanguageChoice(['fr', 'en']), true)
  })
})

describe('translationLanguagesLeft', () => {
  it('leaves out the language of the publication and of its translations', () => {
    assert.deepEqual(
      translationLanguagesLeft({ language: 'fr', translation_languages: [] }, ['fr', 'en']),
      ['en']
    )
    assert.deepEqual(
      translationLanguagesLeft({ language: 'en', translation_languages: ['fr'] }, ['fr', 'en']),
      []
    )
    assert.deepEqual(
      translationLanguagesLeft({ language: 'fr', translation_languages: ['de'] }, [
        'fr',
        'en',
        'de',
        'es'
      ]),
      ['en', 'es']
    )
  })

  it('has nothing to offer when French is the only locale', () => {
    assert.deepEqual(
      translationLanguagesLeft({ language: 'fr', translation_languages: [] }, ['fr']),
      []
    )
  })

  it('never translates a video, which has no language', () => {
    assert.deepEqual(
      translationLanguagesLeft({ language: null, translation_languages: [] }, ['fr', 'en']),
      []
    )
  })

  it('reads a publication without translation_languages as having no translation', () => {
    assert.deepEqual(translationLanguagesLeft({ language: 'fr' }, ['fr', 'en']), ['en'])
  })
})

describe('languageOptions', () => {
  it('labels each language in itself and disables the taken ones', () => {
    assert.deepEqual(languageOptions(['fr', 'en'], ['en']), [
      { value: 'fr', label: 'Français', disabled: false },
      { value: 'en', label: 'English', disabled: true }
    ])
  })

  it('disables nothing by default', () => {
    assert.deepEqual(languageOptions(['en']), [{ value: 'en', label: 'English', disabled: false }])
  })
})
