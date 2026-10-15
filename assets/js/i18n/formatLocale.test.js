import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'
import { fr } from 'date-fns/locale'
import { dateFnsLocale, forActiveLocale, intlLocale } from './formatLocale.js'
import { i18n } from './index.js'

describe('formatLocale', () => {
  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  it('formats French with the tag and the date-fns locale the app always used', () => {
    assert.equal(intlLocale(), 'fr-FR')
    assert.equal(dateFnsLocale(), fr)
  })

  it('picks the entry of the active locale', () => {
    const table = { fr: 'français', en: 'English' }

    assert.equal(forActiveLocale(table), 'français')
    i18n.global.locale.value = 'en'
    assert.equal(forActiveLocale(table), 'English')
  })

  it('falls back on French for a locale with no entry', () => {
    i18n.global.locale.value = 'en'

    assert.equal(forActiveLocale({ fr: 'français' }), 'français')
    assert.equal(intlLocale(), 'fr-FR')
  })
})
