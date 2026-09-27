import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../constants/types.js'

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

/**
 * A frequent search (#1075) as the homepage shows it: « Batteur à Bruxelles » for musicians, « Groupe
 * cherchant un batteur à Liège » for the bands looking for one, and the link that runs it again.
 */
export function frequentSearchLink(search) {
  const lookingForBand = search.type === TYPES_ANNOUNCE_MUSICIAN
  const label = lookingForBand
    ? `Groupe cherchant un ${search.instrument_name.toLocaleLowerCase()} à ${search.location_name}`
    : `${search.instrument_name} à ${search.location_name}`
  return {
    label,
    route: musicianSearchRoute({
      lookingFor: lookingForBand ? LOOKING_FOR_BAND : LOOKING_FOR_MUSICIAN,
      instrument: { id: search.instrument_id },
      city: { name: search.location_name, latitude: search.latitude, longitude: search.longitude }
    })
  }
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

/** The first styles of an announce as the tags of a card, and the others behind its « +N ». */
export function announceStyleTags(announce, limit = 3) {
  const names = announce.styles.map((style) => style.name)
  return { tags: names.slice(0, limit), hidden: names.slice(limit) }
}
