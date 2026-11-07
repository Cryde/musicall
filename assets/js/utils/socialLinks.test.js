import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { socialPlatformIcon } from './socialLinks.js'

describe('socialPlatformIcon', () => {
  it('names an icon PrimeIcons has for each platform', () => {
    assert.equal(socialPlatformIcon('youtube'), 'pi-youtube')
    assert.equal(socialPlatformIcon('tiktok'), 'pi-tiktok')
  })

  it('stands in for Spotify, which PrimeIcons lacks', () => {
    assert.equal(socialPlatformIcon('spotify'), 'pi-headphones')
  })

  it('falls back to a link for a platform it does not know', () => {
    assert.equal(socialPlatformIcon('myspace'), 'pi-link')
  })
})
