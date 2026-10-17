import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import axios from 'axios'
import fr from './fr/index.js'
import { i18n } from './index.js'

// A page with English enabled. Set before locale.js loads, since it reads the attribute once.
globalThis.document = { documentElement: { lang: 'fr', dataset: { enabledLocales: 'fr,en' } } }
const { ENABLED_LOCALES, LOCALE_HEADER, enabledLocalesOf, setLocale } = await import('./locale.js')

function activeState() {
  return {
    locale: i18n.global.locale.value,
    lang: document.documentElement.lang,
    header: axios.defaults.headers.common[LOCALE_HEADER]
  }
}

describe('enabledLocalesOf', () => {
  it('reads the list the page gives', () => {
    assert.deepEqual(enabledLocalesOf({ dataset: { enabledLocales: 'fr,en' } }), ['fr', 'en'])
  })

  it('is French only when the page does not say', () => {
    assert.deepEqual(enabledLocalesOf({ dataset: {} }), ['fr'])
  })

  it('is French only outside a page', () => {
    assert.deepEqual(enabledLocalesOf(undefined), ['fr'])
  })
})

// In order: the first switch to English is the only one that loads its catalogue.
describe('setLocale', () => {
  it('reads the enabled locales from the page', () => {
    assert.deepEqual(ENABLED_LOCALES, ['fr', 'en'])
  })

  it('keeps the last locale asked for when an earlier one is still loading', async () => {
    const english = setLocale('en')
    await setLocale('fr')
    await english

    assert.deepEqual(activeState(), { locale: 'fr', lang: 'fr', header: 'fr' })
  })

  it('switches the messages, the page language and the API calls to English', async () => {
    await setLocale('en')

    assert.deepEqual(activeState(), { locale: 'en', lang: 'en', header: 'en' })
    assert.ok(i18n.global.availableLocales.includes('en'))
  })

  it('falls back to French for a key the English catalogue lacks', () => {
    const quiet = { missingWarn: false }

    assert.equal(i18n.global.t('shared.head.title', {}, quiet), fr.shared.head.title)
  })

  it('ignores a locale that is not enabled', async () => {
    await setLocale('de')

    assert.deepEqual(activeState(), { locale: 'en', lang: 'en', header: 'en' })
  })

  it('switches back to French', async () => {
    await setLocale('fr')

    assert.deepEqual(activeState(), { locale: 'fr', lang: 'fr', header: 'fr' })
  })
})
