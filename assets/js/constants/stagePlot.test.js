import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  ALIGNMENT_TOLERANCE,
  findAlignmentGuides,
  MAX_ROTATION,
  MIN_ROTATION,
  nextQuarterTurn,
  normaliseRotation,
  pointerBearing,
  ROTATION_SNAP_STEP,
  rotationFromPointer,
  rotationGrabOffset,
  SNAP_STEP
} from './stagePlot.js'

/**
 * The rotation maths, which is why those functions are exported rather than living inside
 * StagePlotCanvas.vue: a drag gesture needs a browser, but the arithmetic behind it does not.
 *
 * Run with `npm test`. Node's own runner, so this costs no dependency. The pointer wiring itself
 * still has no coverage, because that would need jsdom and a component harness.
 */

const CENTRE = { x: 100, y: 100 }

/** A point on a circle around CENTRE at the given bearing, for sweeping the whole turn. */
function pointAt(degrees, radius = 90) {
  const radians = (degrees * Math.PI) / 180

  return {
    x: CENTRE.x + radius * Math.cos(radians),
    y: CENTRE.y + radius * Math.sin(radians)
  }
}

describe('pointerBearing', () => {
  // Screen y grows downwards, which is the same direction CSS rotate() turns, so a bearing maps onto
  // a rotation with no sign flip. These four pin that: get the sign wrong and every icon mirrors.
  it('reads clockwise from the positive x axis, matching CSS rotate', () => {
    assert.equal(normaliseRotation(pointerBearing(CENTRE, { x: 200, y: 100 })), 0)
    assert.equal(normaliseRotation(pointerBearing(CENTRE, { x: 100, y: 200 })), 90)
    assert.equal(normaliseRotation(pointerBearing(CENTRE, { x: 0, y: 100 })), 180)
    assert.equal(normaliseRotation(pointerBearing(CENTRE, { x: 100, y: 0 })), 270)
  })

  // The grip sits outside the box so this should not happen, but atan2(0, 0) is 0 rather than NaN and
  // a rotation of NaN would be written straight into the document.
  it('does not produce NaN when the pointer is on the centre', () => {
    assert.equal(Number.isNaN(pointerBearing(CENTRE, CENTRE)), false)
  })
})

describe('normaliseRotation', () => {
  it('folds any angle into the range the server accepts', () => {
    assert.equal(normaliseRotation(360), 0)
    assert.equal(normaliseRotation(-1), 359)
    assert.equal(normaliseRotation(-90), 270)
    assert.equal(normaliseRotation(725), 5)
  })
})

describe('rotationFromPointer', () => {
  // Grabbing the grip must not swing the icon round to meet the pointer, which is what the recorded
  // offset is for. Only snapped angles are used here: an unsnapped one legitimately moves to its
  // nearest stop on the first move.
  it('leaves the angle alone at the moment of the grab', () => {
    for (const rotation of [0, 15, 45, 90, 180, 270, 345]) {
      const point = pointAt(37)
      const offset = rotationGrabOffset(CENTRE, point, rotation)

      assert.equal(rotationFromPointer(CENTRE, point, offset, true), rotation)
    }
  })

  it('snaps to whole steps and reaches every stop over a full turn', () => {
    const snapped = new Set()
    for (let bearing = 0; bearing < 360; bearing += 1) {
      snapped.add(rotationFromPointer(CENTRE, pointAt(bearing), 0, true))
    }

    assert.equal(snapped.size, 360 / ROTATION_SNAP_STEP)
    assert.ok([...snapped].every((angle) => angle % ROTATION_SNAP_STEP === 0))
    // The quarter turns have to be reachable by dragging, not only by the shortcut buttons.
    assert.ok([0, 90, 180, 270].every((angle) => snapped.has(angle)))
  })

  it('gives finer control when snapping is off', () => {
    const free = new Set()
    for (let bearing = 0; bearing < 360; bearing += 1) {
      free.add(rotationFromPointer(CENTRE, pointAt(bearing), 0, false))
    }

    assert.ok(free.size > 360 / ROTATION_SNAP_STEP)
  })

  // Snapping happens before folding, so a bearing near 355 rounds to 360 and then has to come back to
  // 0. Folding first would not help: 359 still rounds up to 360 afterwards.
  it('never yields a value outside the accepted range, snapped or free', () => {
    for (let bearing = -720; bearing <= 720; bearing += 1) {
      for (const snap of [true, false]) {
        const angle = rotationFromPointer(CENTRE, pointAt(bearing), 0, snap)

        assert.ok(Number.isInteger(angle), `${angle} is not a whole degree`)
        assert.ok(angle >= MIN_ROTATION && angle <= MAX_ROTATION, `${angle} is out of range`)
      }
    }
  })
})

describe('findAlignmentGuides', () => {
  const at = (id, x, y) => ({ id, x, y })

  it('finds an alignment on one axis without inventing one on the other', () => {
    const dragged = at('dragged', 0.5, 0.5)
    const others = [at('a', 0.5, 0.2)]

    assert.deepEqual(findAlignmentGuides(dragged, others), { x: 0.5, y: null })
  })

  it('finds both axes when two different elements each line up', () => {
    const dragged = at('dragged', 0.5, 0.5)
    const others = [at('a', 0.5, 0.2), at('b', 0.9, 0.5)]

    assert.deepEqual(findAlignmentGuides(dragged, others), { x: 0.5, y: 0.5 })
  })

  // The line is drawn at the reference element's coordinate, not the dragged one's, so it reads as
  // "that element is on this line" rather than following the pointer.
  it('reports the reference coordinate, not the dragged one', () => {
    const dragged = at('dragged', 0.504, 0.5)
    const others = [at('a', 0.5, 0.1)]

    assert.deepEqual(findAlignmentGuides(dragged, others).x, 0.5)
  })

  // Both orderings, because either one alone passes against an implementation that simply lets the
  // last match win: with the near candidate listed last, "nearest" and "last" agree.
  it('prefers the nearest candidate whichever order they come in', () => {
    const dragged = at('dragged', 0.5, 0.5)
    const near = at('near', 0.502, 0.1)
    const far = at('far', 0.51, 0.1)

    assert.equal(findAlignmentGuides(dragged, [near, far]).x, 0.502)
    assert.equal(findAlignmentGuides(dragged, [far, near]).x, 0.502)
  })

  it('never aligns an element against itself', () => {
    const dragged = at('dragged', 0.5, 0.5)

    assert.deepEqual(findAlignmentGuides(dragged, [dragged]), { x: null, y: null })
  })

  it('ignores a centre just outside the tolerance', () => {
    const dragged = at('dragged', 0.5, 0.5)
    const justOutside = ALIGNMENT_TOLERANCE + 0.0001

    assert.deepEqual(
      findAlignmentGuides(dragged, [at('a', 0.5 + justOutside, 0.5 + justOutside)]),
      { x: null, y: null }
    )
  })

  /**
   * The boundary itself, which is the only value that distinguishes <= from <. Without it an
   * off-by-one on the comparison ships silently, exactly as it did for MAX_ROTATION in #802.
   *
   * Measured from zero deliberately. The obvious construction, a neighbour at 0.5 plus or minus the
   * tolerance, cannot test this: the two directions land either side of the boundary in floating
   * point, 0.0124999... one way and 0.0125000... the other. Subtracting from zero is exact.
   */
  it('includes a centre exactly at the tolerance', () => {
    const dragged = at('dragged', 0, 0)
    const onTheBoundary = at('a', ALIGNMENT_TOLERANCE, ALIGNMENT_TOLERANCE)

    assert.deepEqual(findAlignmentGuides(dragged, [onTheBoundary]), {
      x: ALIGNMENT_TOLERANCE,
      y: ALIGNMENT_TOLERANCE
    })
  })

  // Two elements that both lack an id must still see each other. Filtering self by id rather than by
  // identity would drop this, because undefined equals undefined.
  it('aligns two distinct elements that share an id', () => {
    const dragged = { x: 0.5, y: 0.5 }
    const other = { x: 0.5, y: 0.1 }

    assert.equal(findAlignmentGuides(dragged, [dragged, other]).x, 0.5)
  })

  it('copes with an empty stage', () => {
    assert.deepEqual(findAlignmentGuides(at('dragged', 0.5, 0.5), []), { x: null, y: null })
  })

  /**
   * The tolerance is half a grid step, which means it can never fire on a near miss while snapping is
   * on: two snapped centres are either identical or a full step apart. So a guide during an ordinary
   * drag always means exactly aligned, and only Alt placement can produce the approximate kind.
   */
  it('cannot report a near miss between two snapped positions', () => {
    const dragged = at('dragged', 0.5, 0.5)
    const oneStepAway = at('a', 0.5 + SNAP_STEP, 0.5 + SNAP_STEP)

    assert.deepEqual(findAlignmentGuides(dragged, [oneStepAway]), { x: null, y: null })
    assert.ok(ALIGNMENT_TOLERANCE < SNAP_STEP, 'the tolerance must stay inside one grid step')
  })
})

describe('nextQuarterTurn', () => {
  it('advances to the next quarter turn and wraps', () => {
    assert.equal(nextQuarterTurn(0), 90)
    assert.equal(nextQuarterTurn(90), 180)
    assert.equal(nextQuarterTurn(270), 0)
    assert.equal(nextQuarterTurn(359), 0)
  })

  // The bug this replaced: the old cycle looked the current value up in ROTATIONS, got -1 for an
  // angle a drag had produced, and silently reset it to 0.
  it('aligns an off-grid angle instead of discarding it', () => {
    assert.equal(nextQuarterTurn(47), 90)
    assert.equal(nextQuarterTurn(91), 180)
    assert.equal(nextQuarterTurn(1), 90)
  })
})
