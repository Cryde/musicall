import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { landingAttribution } from './landingAttribution.js'

const OWN_HOST = 'musicall.com'

describe('landingAttribution', () => {
  it('reads the campaign of the landing URL and the host of the referring site', () => {
    assert.deepEqual(
      landingAttribution(
        'https://musicall.com/band-space?utm_source=google&utm_medium=cpc&utm_campaign=pmax-musiciens',
        'https://www.google.com/',
        OWN_HOST
      ),
      {
        utm_source: 'google',
        utm_medium: 'cpc',
        utm_campaign: 'pmax-musiciens',
        referrer_host: 'www.google.com'
      }
    )
  })

  it('keeps only the host of the referring page, never its path or query', () => {
    assert.deepEqual(
      landingAttribution(
        'https://musicall.com/',
        'https://forum.example.org:8443/topic/42?user=alice#reply',
        OWN_HOST
      ),
      { referrer_host: 'forum.example.org' }
    )
  })

  it('lowercases the referring host', () => {
    assert.deepEqual(
      landingAttribution('https://musicall.com/', 'https://WWW.Example.ORG/', OWN_HOST),
      {
        referrer_host: 'www.example.org'
      }
    )
  })

  it('reads a referring app as its host', () => {
    assert.deepEqual(
      landingAttribution('https://musicall.com/', 'android-app://com.google.android.gm/', OWN_HOST),
      { referrer_host: 'com.google.android.gm' }
    )
  })

  it('leaves out a visitor coming from MusicAll itself', () => {
    assert.equal(
      landingAttribution('https://musicall.com/', 'https://musicall.com/forum', OWN_HOST),
      null
    )
    assert.equal(
      landingAttribution('https://musicall.com/', 'https://MusicAll.com/forum', 'MusicAll.com'),
      null
    )
  })

  it('takes www.musicall.com and musicall.com for the same site', () => {
    assert.equal(
      landingAttribution(
        'https://www.musicall.com/',
        'https://musicall.com/forum',
        'www.musicall.com'
      ),
      null
    )
    assert.equal(
      landingAttribution('https://musicall.com/', 'https://www.musicall.com/forum', OWN_HOST),
      null
    )
  })

  it('leaves out Google and Apple sign-in, which a failed attempt comes back from', () => {
    assert.equal(
      landingAttribution('https://musicall.com/login', 'https://accounts.google.com/', OWN_HOST),
      null
    )
    assert.deepEqual(
      landingAttribution(
        'https://musicall.com/login?utm_source=newsletter',
        'https://appleid.apple.com/',
        OWN_HOST
      ),
      { utm_source: 'newsletter' }
    )
  })

  it('keeps another Google site', () => {
    assert.deepEqual(
      landingAttribution('https://musicall.com/', 'https://mail.google.com/', OWN_HOST),
      { referrer_host: 'mail.google.com' }
    )
  })

  it('keeps the campaign when the referrer is MusicAll itself', () => {
    assert.deepEqual(
      landingAttribution(
        'https://musicall.com/?utm_source=newsletter',
        'https://musicall.com/',
        OWN_HOST
      ),
      { utm_source: 'newsletter' }
    )
  })

  it('decodes, trims and lowercases the campaign values, and leaves out a blank one', () => {
    assert.deepEqual(
      landingAttribution(
        'https://musicall.com/?utm_source=%20Newsletter%20&utm_medium=&utm_campaign=Black+Friday%202026',
        '',
        OWN_HOST
      ),
      { utm_source: 'newsletter', utm_campaign: 'black friday 2026' }
    )
  })

  it('says nothing for a visit typed in, without a campaign', () => {
    assert.equal(landingAttribution('https://musicall.com/en/band-space', '', OWN_HOST), null)
  })

  it('leaves out a referrer that is not a URL', () => {
    assert.equal(landingAttribution('https://musicall.com/', 'not a url', OWN_HOST), null)
  })

  it('ignores the other query parameters', () => {
    assert.equal(
      landingAttribution(
        'https://musicall.com/?gclid=abc&utm_content=banner&utm_term=batteur',
        '',
        OWN_HOST
      ),
      null
    )
  })
})
