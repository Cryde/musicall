/**
 * Where the lyrics reader stands in the songs it turns through: « 2 / 5 », the next title for
 * « Ensuite : », and whether there is a song either side.
 *
 * @param {Array<{title: string}>} songs the songs read in turn, a set's in its running order
 * @param {number} index the one on screen
 */
export function lyricsReaderStep(songs, index) {
  const total = songs.length
  const at = Math.min(Math.max(index, 0), Math.max(total - 1, 0))
  return {
    index: at,
    position: total === 0 ? '0 / 0' : `${at + 1} / ${total}`,
    nextTitle: songs[at + 1]?.title ?? null,
    hasPrevious: at > 0,
    hasNext: at < total - 1
  }
}
