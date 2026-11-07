/**
 * The platforms' own players for a profile media (#1317): the dialog and « Écouter » embed them the same way.
 * `embed_id` is what the server parsed: a YouTube id, `kind/id` for Spotify, `user/track` for SoundCloud.
 */

// PrimeIcons has no Spotify icon.
const PLATFORM_ICONS = {
  youtube: 'pi pi-youtube',
  spotify: 'pi pi-headphones',
  soundcloud: 'pi pi-cloud'
}

/** The icon standing in for a media without a stored thumbnail. */
export function mediaPlatformIcon(platform) {
  return PLATFORM_ICONS[platform] ?? 'pi pi-play'
}

const PLATFORM_SEVERITIES = {
  youtube: 'danger',
  soundcloud: 'warn',
  spotify: 'success'
}

/** The colour of a platform's tag. */
export function mediaPlatformSeverity(platform) {
  return PLATFORM_SEVERITIES[platform] ?? 'info'
}

/** The platform a pasted link looks like it comes from, before the server parses it. */
export function detectMediaPlatform(url) {
  const lowered = url?.toLowerCase() ?? ''
  if (lowered.includes('youtube') || lowered.includes('youtu.be')) return 'youtube'
  if (lowered.includes('soundcloud')) return 'soundcloud'
  if (lowered.includes('spotify')) return 'spotify'
  return null
}

const EMBED_ALLOW = {
  youtube:
    'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture',
  spotify: 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture',
  soundcloud: 'autoplay'
}

// Spotify's compact player fits a single track or episode; an album, a playlist, a show or an artist needs its list.
const SPOTIFY_TRACK_HEIGHT = 152
const SPOTIFY_LIST_HEIGHT = 352
const SOUNDCLOUD_COMPACT_HEIGHT = 166
// YouTube asks for a player of at least 200 × 200: in a narrow column the video is letterboxed.
const YOUTUBE_COMPACT_HEIGHT = 200

const encodePath = (path) => path.split('/').map(encodeURIComponent).join('/')

/** The player's address; `compact` is the slim SoundCloud player, without its large artwork. */
export function mediaEmbedUrl(media, { autoplay = false, compact = false } = {}) {
  switch (media?.platform) {
    case 'youtube':
      return `https://www.youtube.com/embed/${encodeURIComponent(media.embed_id)}${autoplay ? '?autoplay=1' : ''}`
    case 'spotify':
      return `https://open.spotify.com/embed/${encodePath(media.embed_id)}`
    case 'soundcloud': {
      const track = encodeURIComponent(`https://soundcloud.com/${media.embed_id}`)
      return `https://w.soundcloud.com/player/?url=${track}&color=%23ff5500&auto_play=${autoplay}&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true&visual=${!compact}`
    }
    default:
      return null
  }
}

export function mediaEmbedAllow(media) {
  return EMBED_ALLOW[media?.platform] ?? ''
}

/** The compact player's height in pixels. */
export function mediaEmbedHeight(media) {
  if (media?.platform === 'spotify') {
    const single = media.embed_id?.startsWith('track/') || media.embed_id?.startsWith('episode/')
    return single ? SPOTIFY_TRACK_HEIGHT : SPOTIFY_LIST_HEIGHT
  }
  return media?.platform === 'soundcloud' ? SOUNDCLOUD_COMPACT_HEIGHT : YOUTUBE_COMPACT_HEIGHT
}
