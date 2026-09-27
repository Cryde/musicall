import { TYPES_ANNOUNCE_MUSICIAN } from '../constants/types.js'

/**
 * The logged in home's rules (#1078), apart from its components so they are tested without a browser.
 */

const EVENING_FROM_HOUR = 18
const MORNING_FROM_HOUR = 5

/** « Bonjour » in the day, « Bonsoir » from the evening until the small hours. */
export function greetingFor(date) {
  const hour = date.getHours()
  return hour >= MORNING_FROM_HOUR && hour < EVENING_FROM_HOUR ? 'Bonjour' : 'Bonsoir'
}

export const MEMBER_LAYOUT_BAND = 'band'
export const MEMBER_LAYOUT_SEARCH = 'search'

/**
 * Which home a member gets: a member with a Band Space gets their band first, one without is most
 * likely still looking for musicians or a band, and gets the search first.
 */
export function memberLayoutFor(spaces) {
  return spaces.length > 0 ? MEMBER_LAYOUT_BAND : MEMBER_LAYOUT_SEARCH
}

/** The space the member last opened, while it is still one of theirs, otherwise their first. */
export function pickBandSpace(spaces, preferredId) {
  return spaces.find((space) => space.id === preferredId) ?? spaces[0] ?? null
}

/**
 * The setlist changed last, with its number of songs: the API lists them by creation, and an item
 * may be a break or a note rather than a song.
 */
export function lastChangedSetlist(setlists) {
  const changedAt = (setlist) => setlist.update_datetime ?? setlist.creation_datetime
  const [latest] = [...setlists].sort((a, b) => changedAt(b).localeCompare(changedAt(a)))
  if (!latest) return null
  return {
    setlist: latest,
    changedAt: changedAt(latest),
    songCount: (latest.items ?? []).filter((item) => item.type === 'song').length
  }
}

/** The tasks still to do, the ones due soonest first and the undated after them. */
export function openTasks(tasks, limit) {
  return tasks
    .filter((task) => task.status !== 'done')
    .sort((a, b) => {
      if (a.due_date && b.due_date) return a.due_date.localeCompare(b.due_date)
      if (a.due_date) return -1
      if (b.due_date) return 1
      return a.title.localeCompare(b.title, 'fr')
    })
    .slice(0, limit)
}

/**
 * A search result in the shape the homepage announce card reads: the search names the author
 * `user` and the instrument `name`, and gives no date.
 */
export function searchResultAsAnnounce(result) {
  return {
    id: result.id,
    type: result.type,
    instrument: { musician_name: result.instrument.name },
    styles: result.styles,
    location_name: result.location_name,
    author: result.user,
    creation_datetime: null
  }
}

/**
 * What « Annonces pour vous » searches for: bands looking for the first instrument of the member's
 * musician profile. None without a profile or an instrument.
 */
export function announceCriteriaFor(musicianProfile) {
  const instrument = musicianProfile?.instruments?.[0]
  if (!instrument) return null
  return {
    type: TYPES_ANNOUNCE_MUSICIAN,
    instrumentId: instrument.instrument_id,
    instrumentName: instrument.instrument_name
  }
}
