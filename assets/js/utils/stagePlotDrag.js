import {
  MAX_SCALE,
  MIN_SCALE,
  rotationFromPointer,
  rotationGrabOffset,
  SCALE_STEP,
  snapFraction,
  toFraction
} from '../constants/stagePlot.js'

/**
 * The three stage plot pointer gestures, held as plain data keyed by element id.
 *
 * The canvas used to keep the element object itself for the length of a drag. That object is only
 * the current one until the editor reseeds: seeding rebuilds the list from the stored document and
 * replaces every entry, so a reseed landing mid gesture left the handlers writing to an orphan
 * while the keyed v-for had already bound the DOM node to the fresh object. The element visibly
 * stopped following the pointer, and everything the rest of the gesture did went nowhere. The
 * alignment guides went with it: the scan skips the dragged element by identity, and an orphan
 * matches none of the entries, so it lined itself up against its own replacement.
 *
 * Resolving the element by id on every event costs one scan of at most MAX_ELEMENTS entries, the
 * order the guide scan already runs at, and what it finds is always the object on screen. Ids are
 * unique and non empty across a plot, which TechRiderStagePlotValidator enforces on the way in, so
 * the scan resolves to exactly one element, or to nothing at all when it has been removed under the
 * gesture. That is the caller's cue to end the gesture, not a reason to throw.
 *
 * The arithmetic lives here rather than in the component because a pointer gesture needs a browser
 * and the numbers behind it do not. See stagePlotDrag.test.js.
 */

/**
 * Alt places freely; without it everything lands on the grid, because a plot where nothing lines up
 * looks careless on a printed page. Shared by the move drag and by the picker's drop, so the two
 * cannot drift apart on what Alt means.
 */
export function placeFraction(value, freePlacement) {
  return freePlacement ? toFraction(value) : snapFraction(value)
}

/** Pixels between a captured centre and a pointer, for the scale ratio. */
export function distanceFrom(centre, point) {
  return Math.hypot(point.x - centre.x, point.y - centre.y)
}

/**
 * @param {{id: string, x: number, y: number}} element
 * @param {{x: number, y: number}} start Where the pointer went down, in stage fractions.
 * @param {number} pointerId
 */
export function startMoveDrag(element, start, pointerId) {
  return {
    elementId: element.id,
    pointerId,
    // Taken once, so grabbing an icon by its edge does not centre it under the pointer.
    offsetX: element.x - start.x,
    offsetY: element.y - start.y
  }
}

/**
 * How far the reseed guarantee goes, for scale and rotate alike. Resolving by id keeps the gesture
 * writing to the element on screen, but the pivot geometry below is measured once at pointerdown.
 * A reseed that brings the same id back at different coordinates therefore leaves the rest of that
 * one gesture turning around where the icon used to sit. Cosmetic, and the next gesture measures
 * again. Recomputing the centre per event would fix rotate on its own, but scale reads
 * startDistance against that same centre, so moving one without re-deriving the other makes the
 * icon jump size mid-drag.
 *
 * @param {{id: string, scale?: number}} element
 * @param {{x: number, y: number}} centre The element's centre in client pixels.
 * @param {number} startDistance Where the grip was grabbed, as a distance from that centre.
 * @param {number} pointerId
 */
export function startScaleDrag(element, centre, startDistance, pointerId) {
  return {
    elementId: element.id,
    pointerId,
    centre,
    startDistance,
    startScale: element.scale ?? 1
  }
}

/**
 * @param {{id: string, rotation?: number}} element
 * @param {{x: number, y: number}} centre The element's centre in client pixels.
 * @param {{x: number, y: number}} point Where the grip was grabbed, in client pixels.
 * @param {number} pointerId
 */
export function startRotateDrag(element, centre, point, pointerId) {
  return {
    elementId: element.id,
    pointerId,
    centre,
    // Recorded once and added back on every move, so grabbing the grip anywhere leaves the icon
    // where it was instead of swinging it round to meet the pointer.
    grabOffset: rotationGrabOffset(centre, point, element.rotation ?? 0)
  }
}

/** The element a gesture is acting on as the list stands now, or null once it is gone. */
export function draggedElement(drag, elements) {
  return elements.find((element) => element.id === drag.elementId) ?? null
}

/**
 * Moves the grabbed element to where the pointer is, offset included.
 *
 * @returns The element moved, so the caller can line the guides up against it, or null when it is
 *   no longer in the list and the gesture has nothing left to act on.
 */
export function moveDraggedElement(drag, elements, position, freePlacement) {
  const element = draggedElement(drag, elements)
  if (!element) return null

  element.x = placeFraction(position.x + drag.offsetX, freePlacement)
  element.y = placeFraction(position.y + drag.offsetY, freePlacement)

  return element
}

/**
 * Resizes the grabbed element from how far the pointer now sits from its centre.
 *
 * An absolute ratio against the grab distance rather than incremental deltas, so the size never
 * drifts away from the pointer over a long drag. Clamped and stepped to what the inspector's slider
 * offers, so dragging cannot produce a value the panel is unable to show or the server would refuse.
 *
 * @returns The element resized, or null when it is no longer in the list.
 */
export function scaleDraggedElement(drag, elements, point) {
  const element = draggedElement(drag, elements)
  if (!element) return null

  const next = (drag.startScale * distanceFrom(drag.centre, point)) / drag.startDistance
  element.scale = Math.min(
    MAX_SCALE,
    Math.max(MIN_SCALE, Math.round(next / SCALE_STEP) * SCALE_STEP)
  )

  return element
}

/**
 * Turns the grabbed element to the pointer's bearing around its own centre.
 *
 * @returns The element turned, or null when it is no longer in the list.
 */
export function rotateDraggedElement(drag, elements, point, snapRotation) {
  const element = draggedElement(drag, elements)
  if (!element) return null

  element.rotation = rotationFromPointer(drag.centre, point, drag.grabOffset, snapRotation)

  return element
}
