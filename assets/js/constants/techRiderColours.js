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
