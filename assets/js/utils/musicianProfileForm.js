/**
 * The musician profile's edit form (#1317): the whole form in the settings and at creation, or one block of it
 * from a pencil on the profile. The PATCH keeps every field it is not sent (#1325), so a block sends only its own.
 */

import { bandRowOf, bandsForm, bandsPayload } from './musicianBands.js'

export const MUSICIAN_BLOCKS = ['availability', 'instruments', 'styles', 'bands']

/** The stored profile as the form holds it; an empty form without one. */
export function musicianForm(profile) {
  return {
    availabilityStatus: profile?.availability_status || null,
    instruments: (profile?.instruments ?? []).map((instrument) => ({
      instrumentId: instrument.instrument_id,
      skillLevel: instrument.skill_level
    })),
    styleIds: (profile?.styles ?? []).map((style) => style.id),
    bands: bandsForm(profile)
  }
}

/** The complete rows only, an instrument chosen twice kept once with its last level. */
export function instrumentsPayload(rows) {
  const seen = new Set()
  return rows
    .filter((row) => row.instrumentId && row.skillLevel)
    .reverse()
    .filter((row) => {
      if (seen.has(row.instrumentId)) return false
      seen.add(row.instrumentId)
      return true
    })
    .reverse()
    .map((row) => ({ instrument_id: row.instrumentId, skill_level: row.skillLevel }))
}

const BLOCK_PAYLOADS = {
  availability: (form) => ({ availability_status: form.availabilityStatus || null }),
  instruments: (form) => ({ instruments: instrumentsPayload(form.instruments) }),
  styles: (form) => ({ style_ids: form.styleIds }),
  bands: (form) => ({ bands: bandsPayload(form.bands) })
}

/** What the form sends: every block, or only those named. */
export function musicianPayload(form, blocks = MUSICIAN_BLOCKS) {
  return Object.assign({}, ...blocks.map((block) => BLOCK_PAYLOADS[block](form)))
}

/** A violation as the form shows it: a band's names its row, since several bands can carry the same message. */
export function musicianViolationMessage(violation, t) {
  const row = bandRowOf(violation.propertyPath)
  return row
    ? t('profile.musician.edit.band_violation', { number: row, message: violation.message })
    : violation.message
}
