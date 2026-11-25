import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  detectMediaPlatform,
  mediaEmbedAllow,
  mediaEmbedHeight,
  mediaEmbedUrl,
  mediaPlatformIcon,
  mediaPlatformSeverity,
  PLATFORMS_READ_BY_APP
} from './mediaEmbed.js'

const youtube = { platform: 'youtube', embed_id: 'dQw4w9WgXcQ' }
const spotifyTrack = { platform: 'spotify', embed_id: 'track/4uLU6hMCjMI75M1A2tKUQC' }
const spotifyAlbum = { platform: 'spotify', embed_id: 'album/1DFixLWuPkv3KT3TnV35m3' }
const soundcloud = { platform: 'soundcloud', embed_id: 'julien/les-heures-creuses' }
const vimeo = { platform: 'vimeo', embed_id: '76979871' }
const unlistedVimeo = { platform: 'vimeo', embed_id: '76979871/8272103f6e' }

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

describe('a Vimeo video (#1411)', () => {
  it('plays in the Vimeo player, starting only when asked', () => {
    assert.equal(mediaEmbedUrl(vimeo), 'https://player.vimeo.com/video/76979871')
    assert.equal(
      mediaEmbedUrl(vimeo, { autoplay: true }),
      'https://player.vimeo.com/video/76979871?autoplay=1'
    )
  })

  it('passes the hash an unlisted video only plays with', () => {
    assert.equal(
      mediaEmbedUrl(unlistedVimeo, { autoplay: true }),
      'https://player.vimeo.com/video/76979871?h=8272103f6e&autoplay=1'
    )
  })

  it('is recognised, allowed full screen, and has its icon and colour', () => {
    assert.equal(
      detectMediaPlatform('https://vimeo.com/76979871', [...PLATFORMS_READ_BY_APP, 'vimeo']),
      'vimeo'
    )
    assert.ok(mediaEmbedAllow(vimeo).includes('fullscreen'))
    assert.equal(mediaPlatformIcon('vimeo'), 'pi pi-vimeo')
    assert.equal(mediaPlatformSeverity('vimeo'), 'info')
    assert.equal(mediaEmbedHeight(vimeo), 200)
  })
})

describe('detectMediaPlatform', () => {
  // The musician and teacher media dialog asks with the default: installed app builds read those media.
  it('never detects Vimeo where only the platforms every reader knows are taken', () => {
    assert.equal(detectMediaPlatform('https://vimeo.com/76979871'), null)
    assert.equal(detectMediaPlatform('https://youtu.be/dQw4w9WgXcQ'), 'youtube')
    assert.equal(detectMediaPlatform('https://soundcloud.com/a/b'), 'soundcloud')
  })
})
