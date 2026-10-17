import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createI18n } from 'vue-i18n'
import { refreshedDefaultTitle } from './defaultTitle.js'

function englishActive() {
  const i18n = createI18n({
    legacy: false,
    locale: 'en',
    fallbackLocale: 'fr',
    messages: {
      fr: { shared: { head: { title: 'MusicAll, le site de la musique' } } },
      en: { shared: { head: { title: 'MusicAll, the music site' } } }
    }
  })

  return i18n.global
}

describe('refreshedDefaultTitle', () => {
  it('replaces the default title of the locale left by the active one', () => {
    assert.equal(
      refreshedDefaultTitle('MusicAll, le site de la musique', englishActive()),
      'MusicAll, the music site'
    )
  })

  it('leaves the active default alone', () => {
    assert.equal(refreshedDefaultTitle('MusicAll, the music site', englishActive()), null)
  })

  it('leaves a page title alone', () => {
    assert.equal(refreshedDefaultTitle('Publications | MusicAll', englishActive()), null)
  })
})
