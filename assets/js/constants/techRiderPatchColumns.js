import { i18n } from '../i18n/index.js'

/**
 * The patch list's columns, in order, as both the editor and the PDF name them (#1089). Mirrors the
 * PHP enum TechRiderPatchColumn, pinned by TechRiderPatchColumnsTest: change a label on one side
 * only and that test fails. The editor shows patchColumnLabel(), from the catalogue.
 */
export const PATCH_LIST_COLUMNS = Object.freeze([
  { field: 'channel', label: 'Canal' },
  { field: 'name', label: 'Nom' },
  { field: 'microphone', label: 'Micro' },
  { field: 'routing', label: 'Routage' },
  { field: 'colour', label: 'Couleur' }
])

/** Mirrors TechRiderPatchDirection::label(). The editor shows patchDirectionLabel(). */
export const PATCH_LIST_DIRECTIONS = Object.freeze({
  inputs: 'Entrées',
  outputs: 'Retours'
})

const COLUMN_LABEL_KEYS = Object.freeze({
  channel: 'band_space_tech_rider.patch_column.channel',
  name: 'band_space_tech_rider.patch_column.name',
  microphone: 'band_space_tech_rider.patch_column.microphone',
  routing: 'band_space_tech_rider.patch_column.routing',
  colour: 'band_space_tech_rider.patch_column.colour'
})

/**
 * The placeholders show only on a row nobody has typed into yet, so an example is never mistaken
 * for a value the PDF would then print as a dash.
 */
const COLUMN_PLACEHOLDER_KEYS = Object.freeze({
  name: 'band_space_tech_rider.patch_column.name_placeholder',
  microphone: 'band_space_tech_rider.patch_column.microphone_placeholder',
  routing: 'band_space_tech_rider.patch_column.routing_placeholder'
})

const DIRECTION_LABEL_KEYS = Object.freeze({
  inputs: 'band_space_tech_rider.patch_direction.inputs',
  outputs: 'band_space_tech_rider.patch_direction.outputs'
})

/** A column's name, or null for a field that is not a column. */
export function patchColumnLabel(field) {
  const key = COLUMN_LABEL_KEYS[field]
  return key ? i18n.global.t(key) : null
}

export function patchColumnPlaceholder(field) {
  const key = COLUMN_PLACEHOLDER_KEYS[field]
  return key ? i18n.global.t(key) : null
}

export function patchDirectionLabel(direction) {
  return i18n.global.t(DIRECTION_LABEL_KEYS[direction])
}
