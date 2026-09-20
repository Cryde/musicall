import { extractUrls } from './autoLink.js'

/**
 * Describes the first YouTube, Spotify, SoundCloud or Bandcamp link of a chat message, so the chat
 * can show a player instead of a bare URL (#975).
 *
 * Matching is done on the parsed hostname against an exact allowlist, never with a regex over the
 * whole URL: `/youtube\.com/` also matches `evil-youtube.com.attacker.net`, and what comes out of
 * here becomes an iframe `src`. Nothing the sender typed is passed through verbatim either, the
 * embed URL is rebuilt from parts that each had to match their own shape.
 *
 * The input is sanitizer output, so a URL arrives HTML escaped: measured on `app.onlybr_sanitizer`,
 * `=` reads `&#61;` and `&` reads `&amp;`, which is enough to make `new URL().searchParams` see no
 * `v` at all. Decoding first is also what makes this agree with the anchor `autoLink` builds, since
 * the browser decodes the same entities out of the `href` before navigating.
 */

const KIND_LABELS = {
  video: 'Vidéo',
  track: 'Morceau',
  album: 'Album',
  playlist: 'Playlist',
  artist: 'Artiste'
}

const PROVIDER_LABELS = {
  youtube: 'YouTube',
  spotify: 'Spotify',
  soundcloud: 'SoundCloud',
  bandcamp: 'Bandcamp'
}

const YOUTUBE_HOSTS = ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtu.be']
const YOUTUBE_ID_PATTERN = /^[A-Za-z0-9_-]{11}$/

const SPOTIFY_HOST = 'open.spotify.com'
const SPOTIFY_KINDS = ['track', 'album', 'playlist', 'artist']
const SPOTIFY_ID_PATTERN = /^[A-Za-z0-9]{22}$/
const SPOTIFY_LOCALE_PATTERN = /^intl-[a-z]{2}$/

const SOUNDCLOUD_HOSTS = ['soundcloud.com', 'm.soundcloud.com']

const BANDCAMP_SUFFIX = '.bandcamp.com'
const BANDCAMP_KINDS = ['track', 'album']

/** What a permalink segment may hold on SoundCloud and Bandcamp alike: letters, digits, `_`, `-`. */
const SLUG_PATTERN = /^[\w-]+$/

/**
 * @param {string} content a message body, as the API returns it
 * @returns {{provider: string, kind: string, embedUrl: ?string, title: string, href: string}|null}
 */
export function findMusicLinkPreview(content) {
  // The first match wins, and only it: five links must not turn a message into five players.
  for (const rawUrl of extractUrls(content)) {
    const url = parseUrl(decodeHtmlEntities(rawUrl))
    if (url === null) {
      continue
    }

    for (const match of [matchYouTube, matchSpotify, matchSoundCloud, matchBandcamp]) {
      const preview = match(url)
      if (preview !== null) {
        return preview
      }
    }
  }

  return null
}

/** The five HTML5 predefined entities. Of them the sanitizer emits `&amp;` and `&gt;`, measured. */
const NAMED_ENTITIES = {
  amp: '&',
  apos: "'",
  gt: '>',
  lt: '<',
  quot: '"'
}

const ENTITY_PATTERN = /&(?:#(\d{1,7})|#[xX]([0-9a-fA-F]{1,6})|([a-zA-Z]+));/g

function decodeHtmlEntities(value) {
  return value.replace(ENTITY_PATTERN, (entity, decimal, hexadecimal, name) => {
    if (name !== undefined) {
      return NAMED_ENTITIES[name.toLowerCase()] ?? entity
    }

    const codePoint = Number.parseInt(decimal ?? hexadecimal, decimal === undefined ? 16 : 10)

    // A lone surrogate or anything past the last plane would throw; leaving it escaped is enough,
    // since the URL then simply fails to match a provider.
    if (codePoint > 0x10ffff || (codePoint >= 0xd800 && codePoint <= 0xdfff)) {
      return entity
    }

    return String.fromCodePoint(codePoint)
  })
}

function parseUrl(value) {
  try {
    const url = new URL(value)

    // `extractUrls` only yields http(s) today, but this value ends up as an iframe `src`, so the
    // scheme is checked where the guarantee is needed rather than borrowed from the caller.
    return url.protocol === 'https:' || url.protocol === 'http:' ? url : null
  } catch {
    return null
  }
}

/** A leading `www.` is the one host variation accepted, so `evil-youtube.com` still misses. */
function bareHost(url) {
  return url.hostname.startsWith('www.') ? url.hostname.slice(4) : url.hostname
}

function pathSegments(url) {
  return url.pathname.split('/').filter((segment) => segment !== '')
}

function describe(provider, kind, embedUrl, href) {
  return {
    provider,
    kind,
    embedUrl,
    title: `${KIND_LABELS[kind]} ${PROVIDER_LABELS[provider]}`,
    href
  }
}

function matchYouTube(url) {
  const host = bareHost(url)
  if (!YOUTUBE_HOSTS.includes(host)) {
    return null
  }

  const id = youTubeVideoId(url, host)
  if (id === null || !YOUTUBE_ID_PATTERN.test(id)) {
    return null
  }

  // `youtube-nocookie.com` rather than `youtube.com`: a link somebody pasted should not plant a
  // profiling cookie on everyone who scrolls past it.
  return describe(
    'youtube',
    'video',
    `https://www.youtube-nocookie.com/embed/${id}`,
    `https://www.youtube.com/watch?v=${id}`
  )
}

function youTubeVideoId(url, host) {
  const segments = pathSegments(url)

  if (host === 'youtu.be') {
    return segments.length === 1 ? segments[0] : null
  }
  if (segments[0] === 'watch') {
    return url.searchParams.get('v')
  }
  if (segments[0] === 'shorts' && segments.length === 2) {
    return segments[1]
  }

  return null
}

function matchSpotify(url) {
  if (bareHost(url) !== SPOTIFY_HOST) {
    return null
  }

  const segments = pathSegments(url)
  // `open.spotify.com/intl-fr/track/<id>` is the same page with the interface language in the path.
  if (SPOTIFY_LOCALE_PATTERN.test(segments[0] ?? '')) {
    segments.shift()
  }

  const [kind, id] = segments
  if (segments.length !== 2 || !SPOTIFY_KINDS.includes(kind) || !SPOTIFY_ID_PATTERN.test(id)) {
    return null
  }

  return describe(
    'spotify',
    kind,
    `https://open.spotify.com/embed/${kind}/${id}`,
    `https://open.spotify.com/${kind}/${id}`
  )
}

function matchSoundCloud(url) {
  if (!SOUNDCLOUD_HOSTS.includes(bareHost(url))) {
    return null
  }

  const segments = pathSegments(url)
  if (segments.length !== 2 || !segments.every((segment) => SLUG_PATTERN.test(segment))) {
    return null
  }

  // The token travels with the link or the player refuses to play what it loads, and an unreleased
  // demo is the thing a band is most likely to share here.
  const token = url.searchParams.get('secret_token')
  const query = token !== null && SLUG_PATTERN.test(token) ? `?secret_token=${token}` : ''

  // The widget is handed the URL rebuilt from the parts above, not the string that arrived.
  const href = `https://soundcloud.com/${segments[0]}/${segments[1]}${query}`
  const embedUrl =
    `https://w.soundcloud.com/player/?url=${encodeURIComponent(href)}` +
    '&color=%23ff5500&auto_play=false&show_teaser=false'

  return describe('soundcloud', 'track', embedUrl, href)
}

/**
 * A card, not a player: the Bandcamp embed needs the numeric release id, which the page URL does not
 * carry and only a server call could resolve.
 */
function matchBandcamp(url) {
  const host = url.hostname
  if (!host.endsWith(BANDCAMP_SUFFIX)) {
    return null
  }

  const artist = host.slice(0, -BANDCAMP_SUFFIX.length)
  if (artist === 'www' || !SLUG_PATTERN.test(artist)) {
    return null
  }

  const segments = pathSegments(url)
  const [kind, slug] = segments
  if (segments.length !== 2 || !BANDCAMP_KINDS.includes(kind) || !SLUG_PATTERN.test(slug)) {
    return null
  }

  return describe('bandcamp', kind, null, `https://${host}/${kind}/${slug}`)
}
