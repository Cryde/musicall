import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { findMusicLinkPreview } from './musicLinkPreview.js'

describe('findMusicLinkPreview', () => {
  describe('YouTube', () => {
    it('reads the id out of a watch URL', () => {
      assert.deepEqual(findMusicLinkPreview('https://www.youtube.com/watch?v=dQw4w9WgXcQ'), {
        provider: 'youtube',
        kind: 'video',
        embedUrl: 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        title: 'Vidéo YouTube',
        href: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'
      })
    })

    it('reads the id out of the youtu.be short form, extra parameters and all', () => {
      const preview = findMusicLinkPreview('écoute https://youtu.be/dQw4w9WgXcQ?t=42 avant mardi')

      assert.equal(preview.embedUrl, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
    })

    it('reads the id out of a shorts URL', () => {
      const preview = findMusicLinkPreview('https://www.youtube.com/shorts/dQw4w9WgXcQ')

      assert.equal(preview.embedUrl, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
    })

    it('accepts the mobile and music subdomains', () => {
      assert.equal(findMusicLinkPreview('https://m.youtube.com/watch?v=dQw4w9WgXcQ').kind, 'video')
      assert.equal(
        findMusicLinkPreview('https://music.youtube.com/watch?v=dQw4w9WgXcQ').kind,
        'video'
      )
    })

    it('refuses an id that is not eleven URL-safe characters', () => {
      assert.equal(findMusicLinkPreview('https://www.youtube.com/watch?v=tooshort'), null)
      assert.equal(findMusicLinkPreview('https://youtu.be/dQw4w9WgXcQextra'), null)
      assert.equal(findMusicLinkPreview('https://youtu.be/dQw4w9WgX.Q'), null)
      assert.equal(findMusicLinkPreview('https://www.youtube.com/watch?list=PL123'), null)
    })

    it('refuses a channel or a bare host, which have nothing to play', () => {
      assert.equal(findMusicLinkPreview('https://www.youtube.com/'), null)
      assert.equal(findMusicLinkPreview('https://www.youtube.com/@musicall'), null)
    })
  })

  describe('Spotify', () => {
    it('embeds a track', () => {
      assert.deepEqual(
        findMusicLinkPreview('https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT'),
        {
          provider: 'spotify',
          kind: 'track',
          embedUrl: 'https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT',
          title: 'Morceau Spotify',
          href: 'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT'
        }
      )
    })

    it('embeds an album, a playlist and an artist under their own name', () => {
      const album = findMusicLinkPreview('https://open.spotify.com/album/4cOdK2wGLETKBW3PvgPWqT')
      const playlist = findMusicLinkPreview(
        'https://open.spotify.com/playlist/4cOdK2wGLETKBW3PvgPWqT'
      )
      const artist = findMusicLinkPreview('https://open.spotify.com/artist/4cOdK2wGLETKBW3PvgPWqT')

      assert.equal(album.title, 'Album Spotify')
      assert.equal(album.embedUrl, 'https://open.spotify.com/embed/album/4cOdK2wGLETKBW3PvgPWqT')
      assert.equal(playlist.title, 'Playlist Spotify')
      assert.equal(artist.title, 'Artiste Spotify')
    })

    it('steps over the locale Spotify puts in the path', () => {
      const preview = findMusicLinkPreview(
        'https://open.spotify.com/intl-fr/track/4cOdK2wGLETKBW3PvgPWqT'
      )

      assert.equal(preview.embedUrl, 'https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT')
    })

    it('refuses an id that is not twenty-two base62 characters', () => {
      assert.equal(findMusicLinkPreview('https://open.spotify.com/track/short'), null)
      assert.equal(
        findMusicLinkPreview('https://open.spotify.com/track/4cOdK2wGLETKBW3Pvg-WqT'),
        null
      )
    })

    it('refuses a kind it has no embed for, and the bare host', () => {
      assert.equal(
        findMusicLinkPreview('https://open.spotify.com/user/4cOdK2wGLETKBW3PvgPWqT'),
        null
      )
      assert.equal(findMusicLinkPreview('https://open.spotify.com'), null)
    })
  })

  describe('SoundCloud', () => {
    it('hands the widget the URL it rebuilt, encoded', () => {
      assert.deepEqual(findMusicLinkPreview('https://soundcloud.com/artist-name/track-slug'), {
        provider: 'soundcloud',
        kind: 'track',
        embedUrl:
          'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fartist-name%2Ftrack-slug&color=%23ff5500&auto_play=false&show_teaser=false',
        title: 'Morceau SoundCloud',
        href: 'https://soundcloud.com/artist-name/track-slug'
      })
    })

    it('carries a secret token through, so an unreleased demo still plays', () => {
      const preview = findMusicLinkPreview(
        'https://soundcloud.com/artist-name/unreleased-demo?secret_token&#61;s-AbCdEf12345'
      )

      assert.equal(
        preview.href,
        'https://soundcloud.com/artist-name/unreleased-demo?secret_token=s-AbCdEf12345'
      )
      assert.equal(
        preview.embedUrl,
        'https://w.soundcloud.com/player/?url=https%3A%2F%2Fsoundcloud.com%2Fartist-name%2Funreleased-demo%3Fsecret_token%3Ds-AbCdEf12345&color=%23ff5500&auto_play=false&show_teaser=false'
      )
    })

    it('drops a secret token that is not a plain token', () => {
      const preview = findMusicLinkPreview(
        'https://soundcloud.com/artist-name/track-slug?secret_token=a%20b'
      )

      assert.equal(preview.href, 'https://soundcloud.com/artist-name/track-slug')
    })

    it('refuses a bare host and a profile, neither of which is a track', () => {
      assert.equal(findMusicLinkPreview('https://soundcloud.com'), null)
      assert.equal(findMusicLinkPreview('https://soundcloud.com/'), null)
      assert.equal(findMusicLinkPreview('https://soundcloud.com/artist-name'), null)
      assert.equal(findMusicLinkPreview('https://soundcloud.com/artist/sets/a-playlist'), null)
    })
  })

  describe('Bandcamp', () => {
    it('describes a card rather than an embed, the release id being unknowable from the URL', () => {
      assert.deepEqual(
        findMusicLinkPreview('https://sleeptoken.bandcamp.com/track/the-summoning'),
        {
          provider: 'bandcamp',
          kind: 'track',
          embedUrl: null,
          title: 'Morceau Bandcamp',
          href: 'https://sleeptoken.bandcamp.com/track/the-summoning'
        }
      )
    })

    it('names an album an album', () => {
      const preview = findMusicLinkPreview('https://sleeptoken.bandcamp.com/album/sundowning')

      assert.equal(preview.title, 'Album Bandcamp')
      assert.equal(preview.href, 'https://sleeptoken.bandcamp.com/album/sundowning')
    })

    it('refuses the site itself and anything that is not a release page', () => {
      assert.equal(findMusicLinkPreview('https://bandcamp.com/track/x'), null)
      assert.equal(findMusicLinkPreview('https://www.bandcamp.com/track/x'), null)
      assert.equal(findMusicLinkPreview('https://sleeptoken.bandcamp.com/music'), null)
      assert.equal(findMusicLinkPreview('https://sleeptoken.bandcamp.com/merch/a-shirt'), null)
    })
  })

  describe('reading the message it is given', () => {
    it('decodes the entities the sanitizer left in a query string', () => {
      // Exactly what `app.onlybr_sanitizer` returns for
      // `https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=PL123`, measured.
      const preview = findMusicLinkPreview(
        'https://www.youtube.com/watch?v&#61;dQw4w9WgXcQ&amp;list&#61;PL123'
      )

      assert.equal(preview.embedUrl, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
    })

    it('decodes the same entities in a Spotify link', () => {
      const preview = findMusicLinkPreview(
        'https://open.spotify.com/track/4cOdK2wGLETKBW3PvgPWqT?si&#61;abc&amp;nd&#61;1'
      )

      assert.equal(preview.embedUrl, 'https://open.spotify.com/embed/track/4cOdK2wGLETKBW3PvgPWqT')
    })

    it('decodes a hexadecimal entity as well as a decimal one', () => {
      const preview = findMusicLinkPreview('https://www.youtube.com/watch?v&#x3D;dQw4w9WgXcQ')

      assert.equal(preview.embedUrl, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
    })

    it('leaves an entity it cannot turn into a character alone rather than throwing', () => {
      // Past the last plane, and a lone surrogate: `String.fromCodePoint` throws on both, and a
      // throw here would blank the whole message list.
      assert.equal(findMusicLinkPreview('https://youtu.be/dQw4w9WgXc&#9999999;'), null)
      assert.equal(findMusicLinkPreview('https://youtu.be/dQw4w9WgXc&#xD800;'), null)
    })

    it('stops at the tag boundary the server put after the link', () => {
      const preview = findMusicLinkPreview(
        'https://youtu.be/dQw4w9WgXcQ<br />\n<span class="chat-mention">@lea</span>'
      )

      assert.equal(preview.embedUrl, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ')
    })

    it('previews the first link it can play and no other', () => {
      const preview = findMusicLinkPreview(
        'https://musicall.com/publications puis https://youtu.be/dQw4w9WgXcQ puis https://soundcloud.com/artist-name/track-slug'
      )

      assert.equal(preview.provider, 'youtube')
    })

    it('has nothing to show for a message with no link at all', () => {
      assert.equal(findMusicLinkPreview('on répète mardi'), null)
      assert.equal(findMusicLinkPreview(''), null)
      assert.equal(findMusicLinkPreview(null), null)
      assert.equal(findMusicLinkPreview(undefined), null)
      assert.equal(findMusicLinkPreview(42), null)
      assert.equal(findMusicLinkPreview({}), null)
    })

    it('has nothing to show for a link to somewhere it does not know', () => {
      assert.equal(findMusicLinkPreview('https://musicall.com/publications/un-article'), null)
      assert.equal(findMusicLinkPreview('https://vimeo.com/123456789'), null)
    })
  })

  describe('refusing what only looks like a supported host', () => {
    it('refuses a lookalike domain', () => {
      assert.equal(findMusicLinkPreview('https://evil-youtube.com/watch?v=dQw4w9WgXcQ'), null)
      assert.equal(
        findMusicLinkPreview('https://youtube.com.attacker.net/watch?v=dQw4w9WgXcQ'),
        null
      )
      assert.equal(
        findMusicLinkPreview('https://open.spotify.com.evil.net/track/4cOdK2wGLETKBW3PvgPWqT'),
        null
      )
      assert.equal(findMusicLinkPreview('https://notbandcamp.com/track/x'), null)
    })

    it('refuses a host smuggled into the credentials, entities and all', () => {
      // `@` comes back from the sanitizer as `&#64;`, so the decoded URL is what the browser would
      // navigate to: everything before the `@` is a username, and the host is the attacker's.
      assert.equal(
        findMusicLinkPreview('https://www.youtube.com&#64;evil.test/watch?v&#61;dQw4w9WgXcQ'),
        null
      )
    })

    it('refuses a scheme it cannot vouch for', () => {
      assert.equal(findMusicLinkPreview('javascript:alert(1)'), null)
      assert.equal(findMusicLinkPreview('data:text/html,<script></script>'), null)
      assert.equal(findMusicLinkPreview('//www.youtube.com/watch?v=dQw4w9WgXcQ'), null)
      assert.equal(findMusicLinkPreview('ftp://soundcloud.com/artist-name/track-slug'), null)
    })
  })
})
