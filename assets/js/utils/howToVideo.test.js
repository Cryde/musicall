import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import en from '../i18n/en/index.js'
import fr from '../i18n/fr/index.js'
import { howToVideo, howToVideoLocales } from './howToVideo.js'

const MESSAGES = { en, fr }

function message(messages, key) {
  return key.split('.').reduce((node, part) => node?.[part], messages)
}

describe('howToVideo', () => {
  it('plays a video from youtube-nocookie in a language it exists in', () => {
    assert.deepEqual(howToVideo('band_space_overview', 'fr'), {
      embedUrl: 'https://www.youtube-nocookie.com/embed/9ahF6sV4bUE?autoplay=1&rel=0',
      titleKey: 'shared.how_to.band_space_overview'
    })
  })

  it('has nothing for a language the video was not made in', () => {
    assert.equal(howToVideo('band_space_overview', 'en'), null)
  })

  it('has nothing for an unknown video', () => {
    assert.equal(howToVideo('not_a_video', 'fr'), null)
  })

  it('titles every video in each language it exists in', () => {
    for (const [key, locales] of howToVideoLocales()) {
      for (const locale of locales) {
        const { titleKey } = howToVideo(key, locale)
        assert.equal(
          typeof message(MESSAGES[locale], titleKey),
          'string',
          `${key} has no ${locale} title`
        )
      }
    }
  })
})
