/**
 * The repertoire's search and its two chips (#1063): « Sans paroles » and « Infos manquantes ». Chips
 * narrow together: both on is a song missing lyrics and something else.
 */

/** No key, no BPM or no duration: what a band fills in before it can print a useful setlist. */
export function hasMissingInfo(song) {
  return !song.tonality || !song.tempo || !song.reference_duration
}

export function filterSongs(
  songs,
  { query = '', withoutLyrics = false, missingInfo = false } = {}
) {
  const term = query.trim().toLowerCase()
  return songs.filter(
    (song) =>
      (!term || song.title.toLowerCase().includes(term)) &&
      (!withoutLyrics || !song.has_lyrics) &&
      (!missingInfo || hasMissingInfo(song))
  )
}

/** What each chip would show, over the whole repertoire rather than the current search. */
export function filterCounts(songs) {
  return {
    withoutLyrics: songs.filter((song) => !song.has_lyrics).length,
    missingInfo: songs.filter(hasMissingInfo).length
  }
}
