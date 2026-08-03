/**
 * Shared vocabulary and limits for the stage plot editor.
 *
 * The numbers mirror `App\Validator\BandSpace\TechRider\TechRiderStagePlot`, so the editor can
 * refuse an out-of-range value at the control rather than at a rejected save. They are pinned
 * against the PHP constants by tests/Unit/Validator/BandSpace/TechRider/TechRiderStagePlotLimitsTest.php.
 */
export const STAGE_PLOT_SCHEMA_VERSION = 1

export const MAX_ELEMENTS = 120
export const MAX_LEGEND_ENTRIES = 20
export const MAX_LABEL_LENGTH = 60

export const MIN_SCALE = 0.25
export const MAX_SCALE = 4.0

/** The step the slider offers, which the on-canvas grip snaps to so the two agree. */
export const SCALE_STEP = 0.25

export const MIN_ASPECT_RATIO = 0.5
export const MAX_ASPECT_RATIO = 3.0

export const DEFAULT_ASPECT_RATIO = 1.4

/** Quarter turns only, matching what the model accepts. */
export const ROTATIONS = Object.freeze([0, 90, 180, 270])

/**
 * The snap grid, as a fraction of the stage. A plot where nothing lines up looks careless on a
 * printed page, so snapping is the default and holding Alt places freely.
 */
export const SNAP_STEP = 0.025

/**
 * How far one arrow key press moves a selected element.
 *
 * Deliberately one grid step, not something smaller: a snapping editor whose keyboard path drifts
 * off the grid produces exactly the misaligned page snapping exists to prevent. Holding Alt gives
 * the fine step, mirroring how Alt frees a pointer drag from the grid, so the keyboard has the
 * same three modes the pointer does.
 */
export const NUDGE_STEP = SNAP_STEP
export const FINE_NUDGE_STEP = 0.005
export const COARSE_NUDGE_STEP = 0.1

const HORIZONTAL_BANDS = [
  { limit: 1 / 3, label: 'à gauche' },
  { limit: 2 / 3, label: 'au centre' },
  { limit: Number.POSITIVE_INFINITY, label: 'à droite' }
]

// y grows downstage-to-upstage on screen: 0 is the back wall, 1 is the audience edge, which is
// why "avant" is the high end rather than the low one.
const VERTICAL_BANDS = [
  { limit: 1 / 3, label: 'au fond' },
  { limit: 2 / 3, label: 'au milieu' },
  { limit: Number.POSITIVE_INFINITY, label: "à l'avant" }
]

function bandLabel(bands, value) {
  return bands.find((band) => value < band.limit).label
}

/**
 * Describes a position in words, for example "au fond au centre".
 *
 * One function, used by both an element's `aria-label` and the plot's text summary, so a screen
 * reader is never told two different things about the same element. Coordinates are meaningless
 * read aloud, and this is the only description a non-sighted user gets of a drawing.
 */
export function describePosition(x, y) {
  return `${bandLabel(VERTICAL_BANDS, y)} ${bandLabel(HORIZONTAL_BANDS, x)}`
}

/** Rounded to the stored precision so a drag does not write fifteen decimal places. */
export function toFraction(value) {
  return Math.round(Math.min(1, Math.max(0, value)) * 1000) / 1000
}

export function snapFraction(value) {
  return toFraction(Math.round(value / SNAP_STEP) * SNAP_STEP)
}
