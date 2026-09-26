import { TYPES_ANNOUNCE_BAND, TYPES_ANNOUNCE_MUSICIAN } from '../constants/types.js'

/** What a search looked for, as the admin reads it: the type is who posted the announces searched. */
export function searchedForLabel(type) {
  if (type === TYPES_ANNOUNCE_BAND) return 'Musiciens'
  if (type === TYPES_ANNOUNCE_MUSICIAN) return 'Groupes'
  return 'Tout'
}

export const AI_OUTCOMES = Object.freeze({
  filters: { label: 'Filtres trouvés', severity: 'success' },
  nothing: { label: 'Rien compris', severity: 'warn' },
  failed: { label: 'Échec', severity: 'danger' }
})

/** A part of a whole as a whole percent, « 0 % » rather than NaN for an empty period. */
export function percentOf(part, whole) {
  return whole > 0 ? `${Math.round((part / whole) * 100)} %` : '0 %'
}
