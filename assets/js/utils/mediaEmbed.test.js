import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  detectMediaPlatform,
  mediaEmbedAllow,
  mediaEmbedHeight,
  mediaEmbedUrl,
  mediaPlatformIcon,
  mediaPlatformSeverity
} from './mediaEmbed.js'

const youtube = { platform: 'youtube', embed_id: 'dQw4w9WgXcQ' }
const spotifyTrack = { platform: 'spotify', embed_id: 'track/4uLU6hMCjMI75M1A2tKUQC' }
const spotifyAlbum = { platform: 'spotify', embed_id: 'album/1DFixLWuPkv3KT3TnV35m3' }
const soundcloud = { platform: 'soundcloud', embed_id: 'julien/les-heures-creuses' }

describe('mediaEmbedUrl', () => {
  it('starts a YouTube video only when asked', () => {
    assert.equal(mediaEmbedUrl(youtube), 'https://www.youtube.com/embed/dQw4w9WgXcQ')
    assert.equal(
      mediaEmbedUrl(youtube, { autoplay: true }),
      'https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1'
    )
  })

  it('keeps the kind of a Spotify embed in its path', () => {
    assert.equal(
      mediaEmbedUrl(spotifyTrack),
      'https://open.spotify.com/embed/track/4uLU6hMCjMI75M1A2tKUQC'
    )
  })

  it('passes the SoundCloud track encoded, with autoplay and the compact player as asked', () => {
    assert.equal(
      mediaEmbedUrl(soundcloud, { autoplay: true, compact: true }),
      'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fjulien%2Fles-heures-creuses&color=%23ff5500&auto_play=true&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=false'
    )
    assert.ok(mediaEmbedUrl(soundcloud).includes('&auto_play=false&'))
    assert.ok(mediaEmbedUrl(soundcloud).endsWith('&visual=true'))
  })

  it('has no player for a platform it does not know', () => {
    assert.equal(mediaEmbedUrl({ platform: 'bandcamp', embed_id: 'x' }), null)
    assert.equal(mediaEmbedUrl(null), null)
  })
})

describe('mediaEmbedHeight', () => {
  it('gives Spotify its compact player for a track and its list for the rest', () => {
    assert.equal(mediaEmbedHeight(spotifyTrack), 152)
    assert.equal(mediaEmbedHeight(spotifyAlbum), 352)
    assert.equal(mediaEmbedHeight({ platform: 'spotify', embed_id: 'episode/abc' }), 152)
  })

  it('gives SoundCloud its compact player and YouTube the 200 px it asks for', () => {
    assert.equal(mediaEmbedHeight(soundcloud), 166)
    assert.equal(mediaEmbedHeight(youtube), 200)
  })
})

describe('mediaEmbedAllow', () => {
  it('asks each platform only for what its player uses', () => {
    assert.equal(mediaEmbedAllow(soundcloud), 'autoplay')
    assert.ok(mediaEmbedAllow(youtube).includes('encrypted-media'))
    assert.equal(mediaEmbedAllow({ platform: 'bandcamp' }), '')
  })
})

describe('mediaPlatformIcon', () => {
  it('stands in for each platform, Spotify with headphones since PrimeIcons has no Spotify icon', () => {
    assert.equal(mediaPlatformIcon('youtube'), 'pi pi-youtube')
    assert.equal(mediaPlatformIcon('spotify'), 'pi pi-headphones')
    assert.equal(mediaPlatformIcon('bandcamp'), 'pi pi-play')
  })
})

describe('mediaPlatformSeverity', () => {
  it('colours each platform, and anything else neutrally', () => {
    assert.equal(mediaPlatformSeverity('youtube'), 'danger')
    assert.equal(mediaPlatformSeverity('soundcloud'), 'warn')
    assert.equal(mediaPlatformSeverity('spotify'), 'success')
    assert.equal(mediaPlatformSeverity('deezer'), 'info')
  })
})

describe('detectMediaPlatform', () => {
  it('reads the platform from a pasted link, short YouTube links included', () => {
    assert.equal(detectMediaPlatform('https://www.YouTube.com/watch?v=dQw4w9WgXcQ'), 'youtube')
    assert.equal(detectMediaPlatform('https://youtu.be/dQw4w9WgXcQ'), 'youtube')
    assert.equal(detectMediaPlatform('https://soundcloud.com/artist/track'), 'soundcloud')
    assert.equal(detectMediaPlatform('https://open.spotify.com/track/abc'), 'spotify')
  })

  it('names none for another link or an empty field', () => {
    assert.equal(detectMediaPlatform('https://example.com'), null)
    assert.equal(detectMediaPlatform(''), null)
    assert.equal(detectMediaPlatform(null), null)
  })
})
