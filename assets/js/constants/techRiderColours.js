import { i18n } from '../i18n/index.js'

/**
 * The only colours a tech rider may use, for text emphasis and patch list rows today and for
 * stage plot items once #769 lands.
 *
 * A closed palette rather than a colour picker, for two reasons. Whatever renders the export
 * receives a value from a known list instead of arbitrary user CSS, and one definition keeps
 * the colours identical wherever they appear.
 *
 * Emphasis here is load bearing, not decoration: a real rider marks the one line that ruins
 * the show in red, and that has to survive into the exported document.
 *
 * This list is the client half of a pair: `App\Enum\BandSpace\TechRiderColour` is the other,
 * and the export renderer reads the hex from there. They are kept in step by
 * tests/Unit/Enum/BandSpace/TechRiderColourPaletteTest.php, which explains why the palette is
 * duplicated rather than served from an endpoint. Edit both, in the same order.
 *
 * `label` is the PHP mirror that test reads; the editor shows techRiderColourLabel() instead.
 */
export const TECH_RIDER_COLOURS = Object.freeze([
  { value: 'red', label: 'Rouge', hex: '#dc2626' },
  { value: 'orange', label: 'Orange', hex: '#ea580c' },
  { value: 'yellow', label: 'Jaune', hex: '#ca8a04' },
  { value: 'green', label: 'Vert', hex: '#16a34a' },
  { value: 'cyan', label: 'Cyan', hex: '#0891b2' },
  { value: 'purple', label: 'Violet', hex: '#7c3aed' },
  { value: 'grey', label: 'Gris', hex: '#6b7280' }
])

/** Hex values only, for validating what the editor is allowed to apply. */
export const TECH_RIDER_COLOUR_HEXES = Object.freeze(TECH_RIDER_COLOURS.map((colour) => colour.hex))

const COLOUR_LABEL_KEYS = Object.freeze({
  red: 'band_space_tech_rider.colour.red',
  orange: 'band_space_tech_rider.colour.orange',
  yellow: 'band_space_tech_rider.colour.yellow',
  green: 'band_space_tech_rider.colour.green',
  cyan: 'band_space_tech_rider.colour.cyan',
  purple: 'band_space_tech_rider.colour.purple',
  grey: 'band_space_tech_rider.colour.grey'
})

/** A palette colour's name as the editor shows it, « Aucune » for no colour or an unknown one. */
export function techRiderColourLabel(value) {
  return i18n.global.t(COLOUR_LABEL_KEYS[value] ?? 'band_space_tech_rider.colour.none')
}
