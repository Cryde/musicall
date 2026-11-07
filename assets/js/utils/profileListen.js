/** « Écouter {name} » on a member's profile (#1317): their media as one playlist. */

/** The musician media then the teacher media, each in the order the member set. */
export function listenPlaylist(profile) {
  return [...(profile?.musician?.media ?? []), ...(profile?.teacher?.media ?? [])]
}

/** What a track is called in the list: its title, or its platform when it has none. */
export function trackLabel(media) {
  return media.title || media.platform_label
}

/** The track ‹ or › leads to, going round from the last to the first and back. */
export function stepIndex(length, index, step) {
  return (((index + step) % length) + length) % length
}

/** Where a track is in the list, or -1 once it has left it. */
export function trackIndex(tracks, id) {
  return tracks.findIndex((track) => track.id === id)
}
