// The how-to videos, filmed by the /tuto skill and published on MusicAll's YouTube channel. One id per
// language the video exists in: a language without one hides the link, since the captions are in the video.
const HOW_TO_VIDEOS = {
  band_space_overview: { titleKey: 'shared.how_to.band_space_overview', ids: { fr: '9ahF6sV4bUE' } }
}

/** The player URL and dialog title of a how-to video in this language, or null when it has none. */
export function howToVideo(key, locale) {
  const video = HOW_TO_VIDEOS[key]
  const id = video?.ids[locale]
  if (!id) {
    return null
  }

  // `youtube-nocookie.com`, like the music links in chat, so opening it plants no profiling cookie.
  // `rel=0` keeps the suggestions shown at the end to MusicAll's own channel.
  return {
    embedUrl: `https://www.youtube-nocookie.com/embed/${id}?autoplay=1&rel=0`,
    titleKey: video.titleKey
  }
}
