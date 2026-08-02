/**
 * The only colours a tech rider may use, for section text today and for patch list rows and
 * stage plot items once #768 and #769 land.
 *
 * A closed palette rather than a colour picker, for two reasons. Whatever renders the export
 * receives a value from a known list instead of arbitrary user CSS, and one definition keeps
 * the colours identical wherever they appear.
 *
 * Emphasis here is load bearing, not decoration: a real rider marks the one line that ruins
 * the show in red, and that has to survive into the exported document.
 *
 * #768 introduces the matching PHP enum `TechRiderColour`. Its cases and hex values must stay
 * in step with this list, and at that point the palette should move behind an endpoint so
 * there is a single source rather than two that agree by convention.
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
