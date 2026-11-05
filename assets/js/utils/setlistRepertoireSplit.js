/**
 * The repertoire beside a running order: the songs matching the search, split between those not yet in
 * the set and those already in it. A song already in the set stays addable, for an encore.
 *
 * @param {Array<{id: string, title: string}>} songs the repertoire
 * @param {Array<{type: string, song?: {id: string}}>} items the set's running order
 * @param {string} query the search, matched on the title, case insensitive
 * @returns {{available: Array, inSetlist: Array}}
 */
export function splitRepertoire(songs, items, query = '') {
  const term = query.trim().toLowerCase()
  const matching = term ? songs.filter((song) => song.title.toLowerCase().includes(term)) : songs
  const inSet = new Set(
    items.filter((item) => item.type === 'song' && item.song).map((item) => item.song.id)
  )
  return {
    available: matching.filter((song) => !inSet.has(song.id)),
    inSetlist: matching.filter((song) => inSet.has(song.id))
  }
}
