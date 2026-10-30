/**
 * The « Paroles » list (#1261): its search and filter, and who can be linked to whom. A song holds
 * one set of lyrics, so a song is linkable when no lyrics point at it. `has_lyrics` cannot say so:
 * it is false for a song linked to empty lyrics, which the server still refuses to link again.
 */

const isLinked = (item) => item.song !== null

/** @param {'all'|'linked'|'unlinked'} filter */
export function filterLyrics(lyrics, { query = '', filter = 'all' } = {}) {
  const term = query.trim().toLowerCase()
  return lyrics.filter(
    (item) =>
      (!term || item.title.toLowerCase().includes(term)) &&
      (filter === 'all' || (filter === 'linked') === isLinked(item))
  )
}

/** What each filter would show, over every set of lyrics rather than the current search. */
export function lyricsCounts(lyrics) {
  const linked = lyrics.filter(isLinked).length
  return { all: lyrics.length, linked, unlinked: lyrics.length - linked }
}

/** Songs still in the repertoire that no lyrics are linked to yet. */
export function linkableSongs(songs, lyrics) {
  const taken = new Set(lyrics.map((item) => item.song?.id).filter(Boolean))
  return songs.filter((song) => !song.archive_datetime && !taken.has(song.id))
}

/** In the trash, or on a song in the trash: only « Délier » is left, which frees them. */
export function isLyricsReadOnly(lyrics) {
  return Boolean(lyrics?.archive_datetime || lyrics?.song?.archive_datetime)
}
