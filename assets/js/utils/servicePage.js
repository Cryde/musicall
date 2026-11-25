/** What every kind's page sums up the same way (#1410, #1411), kept out of the components so it is tested. */

import { cheapestPricing } from './teacherPage.js'

/** The specialities picked, in the order the kind lists them (`all`), whatever order they were ticked in. */
export function orderedSpecialities(all, picked) {
  return all.filter((speciality) => picked?.includes(speciality))
}

/**
 * The price a sound engineer's or a producer's header leads with (#1410, #1413), as Service::leadOffer() picks it for
 * a kind that leads with its track price: the cheapest per track, or the cheapest of any unit without one.
 */
export function headlineOffer(offers) {
  if (!Array.isArray(offers) || offers.length === 0) return null
  const tracks = offers.filter((offer) => offer.unit === 'track')
  return cheapestPricing(tracks.length ? tracks : offers)
}
