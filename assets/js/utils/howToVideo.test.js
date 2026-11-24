import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { howToVideo } from './howToVideo.js'

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
    assert.equal(howToVideo('agenda', 'fr'), null)
  })
})
