import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../constants/types.js'
import { formatStyles } from './styles.js'

export const LOOKING_FOR_MUSICIAN = 'musician'
export const LOOKING_FOR_BAND = 'band'

// A SelectButton reads a null value as nothing selected, so « Toutes » needs a value of its own.
export const ANNOUNCE_FILTER_ALL = 'all'

/** The announce type a homepage filter asks the API for, none for all of them. */
export function announceTypeForFilter(filter) {
  return filter === ANNOUNCE_FILTER_ALL ? null : filter
}

/**
 * The homepage search form as a link into the musician search (#1074), which reads the same query
 * the search page writes. An announce's type names who posted it, so looking for a musician means
 * the announces musicians posted to find a band, and the other way round.
 *
 * The city goes only with its coordinates, as the search page ignores a partial location.
 */
export function musicianSearchRoute({ lookingFor, instrument = null, city = null }) {
  const type = lookingFor === LOOKING_FOR_BAND ? TYPES_ANNOUNCE_MUSICIAN : TYPES_ANNOUNCE_BAND
  const query = { type: String(type) }
  if (instrument?.id) query.instrument = instrument.id
  if (city?.name && city.latitude != null && city.longitude != null) {
    query.lat = String(city.latitude)
    query.lng = String(city.longitude)
    query.location = city.name
  }
  return { name: 'app_search_musician', query }
}

/** An announce card's title, from who posted it and the instrument it is about. */
export function announceHeadline(announce) {
  const instrument = announce.instrument.musician_name
  return announce.type === TYPES_ANNOUNCE_MUSICIAN
    ? `Groupe cherche un ${instrument.toLocaleLowerCase()}`
    : `${instrument} cherche un groupe`
}

/** Who posted the announce, for the badge on its card. */
export function announceKindLabel(announce) {
  return announce.type === TYPES_ANNOUNCE_MUSICIAN ? 'Groupe' : 'Musicien·ne'
}

/** The first styles of an announce, and how many more there are, for the tags of a card. */
export function announceStyleTags(announce, limit = 3) {
  const { remaining } = formatStyles(announce.styles, limit)
  return {
    tags: announce.styles.slice(0, limit).map((style) => style.name),
    remaining: Math.max(remaining, 0)
  }
}
