import { isAnnounceLanguage } from './announceLanguages.js'

/**
 * « Nouvelle annonce » filled in by a visitor, kept over sign-in as a pending action (#1306) and reopened on the
 * search page, ready to publish.
 */
export const ANNOUNCE_CREATE = 'announce.create'

const ANNOUNCE_TYPES = ['band', 'musician']

/**
 * The form's values as plain values: ids for the instrument and styles, which the lists give back, and the city
 * as the geocoder answered it, since it cannot be looked up again from an id.
 */
export function announceDraftPayload({ type, instrument, styles, location, note, languages }) {
  return {
    type: type ?? null,
    instrumentId: instrument?.id ?? null,
    styleIds: styles.map((style) => style.id),
    location: location
      ? {
          name: location.name ?? null,
          fullName: location.fullName ?? null,
          latitude: location.latitude,
          longitude: location.longitude
        }
      : null,
    note: note ?? '',
    languages: [...languages]
  }
}

/**
 * The props that reopen `AddAnnounceModal` on what the visitor had filled in. A value that no longer fits (an
 * instrument or style gone from the lists, a malformed city) is left out, so the modal asks for it again.
 *
 * @param {unknown} payload  read back from localStorage
 * @param {{ instruments: Array<{id: string}>, styles: Array<{id: string}> }} lists
 */
export function announceModalProps(payload, { instruments, styles }) {
  const draft = payload && typeof payload === 'object' ? payload : {}
  const styleIds = Array.isArray(draft.styleIds) ? draft.styleIds : []
  const languages = Array.isArray(draft.languages) ? draft.languages : []

  return {
    initialType: ANNOUNCE_TYPES.includes(draft.type) ? draft.type : null,
    initialInstrument:
      instruments.find((instrument) => instrument.id === draft.instrumentId) ?? null,
    initialStyles: styleIds
      .map((id) => styles.find((style) => style.id === id))
      .filter((style) => style !== undefined),
    initialLocation: isCity(draft.location) ? { ...draft.location } : null,
    initialNote: typeof draft.note === 'string' ? draft.note : '',
    initialLanguages: [...new Set(languages.filter(isAnnounceLanguage))]
  }
}

function isCity(location) {
  return (
    location !== null &&
    typeof location === 'object' &&
    typeof location.name === 'string' &&
    typeof location.fullName === 'string' &&
    Number.isFinite(location.latitude) &&
    Number.isFinite(location.longitude)
  )
}
