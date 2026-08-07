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

/**
 * Any whole degree from 0 to 359 is stored, so these four are shortcuts rather than the domain.
 * They stay because a quarter turn is the common case, one click beats aiming a slider at it, and
 * they are the only sane keyboard path: arrows on a 360 stop slider would need ninety presses.
 */
export const MIN_ROTATION = 0
export const MAX_ROTATION = 359
export const ROTATIONS = Object.freeze([0, 90, 180, 270])

/**
 * What the on-canvas rotation grip snaps to, with Alt giving whole degrees, mirroring how Alt frees
 * a move drag from the position grid. Fifteen divides 90, so snapped dragging can always land on a
 * quarter turn, which is what TechRiderStagePlotLimitsTest pins.
 */
export const ROTATION_SNAP_STEP = 15
export const FINE_ROTATION_STEP = 1

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

/**
 * How near two centres have to be before a guide is drawn between them.
 *
 * Half a grid step, so it can never fight the grid: two positions that are both on the grid are
 * either identical or a whole step apart, which is further than this, so a guide between two snapped
 * elements always means exactly aligned.
 *
 * That is as far as the guarantee goes, and it is worth being precise about. It does not extend to
 * the whole document, because an element can legitimately sit off the grid: Alt frees a drag from it,
 * and FINE_NUDGE_STEP is 0.005, which is not a multiple of SNAP_STEP. So a snapped element dragged
 * next to a neighbour nudged one fine step off 0.5 is 0.005 away, inside this tolerance and not
 * aligned. The guide then slightly overstates the precision, which is tolerable only because guides
 * inform and never move anything.
 */
export const ALIGNMENT_TOLERANCE = SNAP_STEP / 2

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

/**
 * The centres the dragged element lines up with, as `{ x, y }`, either being null for no alignment.
 *
 * Returns the *other* element's coordinate rather than the dragged one's, because the line has to be
 * drawn where the reference sits: it says "the batterie is on this line", which is information. A
 * line drawn at the dragged element's own coordinate would follow the pointer and say nothing.
 *
 * Centres only. The document stores no width or height, so an element's rendered box is its icon's
 * intrinsic ratio times its scale, and asking for edges would mean measuring every element from the
 * DOM on every pointer move. Centres are also what a stage plot means by aligned: two wedges at the
 * same depth, a mic in front of an amp.
 *
 * Nothing is moved here. Guides inform; the grid is what keeps a plot tidy.
 */
export function findAlignmentGuides(dragged, others, tolerance = ALIGNMENT_TOLERANCE) {
  let x = null
  let y = null
  let nearestX = Number.POSITIVE_INFINITY
  let nearestY = Number.POSITIVE_INFINITY

  for (const other of others) {
    // An element always aligns with itself, which is never worth drawing. By identity rather than by
    // id: the caller is dragging one of the entries in this very list, so identity is exact, while
    // comparing ids silently drops a real alignment between two elements that share an id or, worse,
    // between two that both lack one, since undefined equals undefined.
    if (other === dragged) {
      continue
    }

    const dx = Math.abs(other.x - dragged.x)
    if (dx <= tolerance && dx < nearestX) {
      nearestX = dx
      x = other.x
    }

    const dy = Math.abs(other.y - dragged.y)
    if (dy <= tolerance && dy < nearestY) {
      nearestY = dy
      y = other.y
    }
  }

  return { x, y }
}

/**
 * Folds any angle into the 0 to 359 whole degrees the server stores, so a drag that crosses twelve
 * o'clock or turns backwards cannot produce 360 or a negative.
 */
export function normaliseRotation(degrees) {
  return ((Math.round(degrees) % 360) + 360) % 360
}

/**
 * The pointer's bearing from a centre, in degrees.
 *
 * Screen y grows downwards, which is the same direction CSS `rotate()` turns, so this maps onto a
 * rotation with no sign flip.
 */
export function pointerBearing(centre, point) {
  return (Math.atan2(point.y - centre.y, point.x - centre.x) * 180) / Math.PI
}

/**
 * How far the element's rotation runs ahead of the pointer at the moment it is grabbed.
 *
 * Recorded once on pointerdown and added back on every move, so grabbing the grip anywhere leaves
 * the icon where it was instead of snapping it round to meet the pointer.
 */
export function rotationGrabOffset(centre, point, currentRotation) {
  return (currentRotation ?? 0) - pointerBearing(centre, point)
}

/** Where a rotation drag lands. Snapping is the default; Alt asks for whole degrees. */
export function rotationFromPointer(centre, point, grabOffset, snap = true) {
  const step = snap ? ROTATION_SNAP_STEP : FINE_ROTATION_STEP
  const bearing = pointerBearing(centre, point) + grabOffset

  // Snap first, then fold: rounding 355 to the nearest fifteen gives 360, which is not a value the
  // server accepts.
  return normaliseRotation(Math.round(bearing / step) * step)
}

/**
 * The next quarter turn clockwise from any angle, for the shortcut button.
 *
 * It used to look the current value up in ROTATIONS, which returned -1 for anything off the list and
 * so reset it to 0. Harmless while nothing could produce an off-list angle, and wrong the moment a
 * drag can.
 */
export function nextQuarterTurn(current) {
  return normaliseRotation((Math.floor(normaliseRotation(current) / 90) + 1) * 90)
}
