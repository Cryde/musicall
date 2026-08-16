import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  distanceFrom,
  draggedElement,
  moveDraggedElement,
  placeFraction,
  rotateDraggedElement,
  scaleDraggedElement,
  startMoveDrag,
  startRotateDrag,
  startScaleDrag
} from './stagePlotDrag.js'

/**
 * These pin the fix for the mid gesture reseed: the three drags used to hold the element object
 * itself, and RiderStagePlotEditor.seed() replaces every object rather than mutating it. A save
 * landing while a fresh drag had started but not yet dirtied the document reseeded under the
 * gesture, and the handlers went on writing to an orphan while the keyed v-for had already bound
 * the DOM node to the fresh one. The element stopped following the pointer for the rest of the
 * gesture.
 *
 * Run with `npm test`. Node's own runner, so this costs no dependency. The pointer wiring itself
 * still has no coverage, because that would need jsdom and a component harness.
 */

function element(id, values = {}) {
  return { id, icon: 'ampli', x: 0.5, y: 0.5, scale: 1, rotation: 0, ...values }
}

/** What seed() does to the list: the same document, rebuilt as brand new objects. */
function reseed(elements) {
  return elements.map((entry) => ({ ...entry }))
}

describe('draggedElement', () => {
  it('resolves the element a gesture was started on', () => {
    const elements = [element('a'), element('b')]
    const drag = startMoveDrag(elements[1], { x: 0.5, y: 0.5 }, 1)

    assert.equal(draggedElement(drag, elements), elements[1])
  })

  it('resolves the fresh object after a reseed, never the one the gesture started on', () => {
    const elements = [element('a'), element('b')]
    const grabbed = elements[0]
    const drag = startMoveDrag(grabbed, { x: 0.5, y: 0.5 }, 1)

    const reseeded = reseed(elements)

    assert.equal(draggedElement(drag, reseeded), reseeded[0])
    assert.notEqual(draggedElement(drag, reseeded), grabbed)
  })

  it('returns null when the element left the list, rather than throwing', () => {
    const drag = startMoveDrag(element('a'), { x: 0.5, y: 0.5 }, 1)

    assert.equal(draggedElement(drag, [element('b')]), null)
    assert.equal(draggedElement(drag, []), null)
  })
})

describe('startMoveDrag', () => {
  it('holds the id and not the element, so a reseed has nothing to orphan', () => {
    const drag = startMoveDrag(element('a', { x: 0.5, y: 0.75 }), { x: 0.25, y: 0.75 }, 7)

    assert.deepEqual(drag, { elementId: 'a', pointerId: 7, offsetX: 0.25, offsetY: 0 })
  })
})

describe('moveDraggedElement', () => {
  it('moves the element the user grabbed after a reseed has replaced it', () => {
    const elements = [element('a', { x: 0.4, y: 0.4 }), element('b', { x: 0.8, y: 0.8 })]
    const grabbed = elements[0]
    const drag = startMoveDrag(grabbed, { x: 0.4, y: 0.4 }, 1)

    // The save from a previous edit lands, the editor reseeds, and the drag is still running.
    const reseeded = reseed(elements)
    const moved = moveDraggedElement(drag, reseeded, { x: 0.6, y: 0.5 }, false)

    assert.equal(moved, reseeded[0])
    assert.equal(reseeded[0].x, 0.6)
    assert.equal(reseeded[0].y, 0.5)
    // The object the gesture started on is the one that used to be moved instead, invisibly.
    assert.equal(grabbed.x, 0.4)
    assert.equal(grabbed.y, 0.4)
  })

  it('keeps the grab offset across a reseed, so the element does not jump under the pointer', () => {
    const elements = [element('a', { x: 0.4, y: 0.4 })]
    const drag = startMoveDrag(elements[0], { x: 0.35, y: 0.35 }, 1)

    const reseeded = reseed(elements)
    moveDraggedElement(drag, reseeded, { x: 0.55, y: 0.55 }, false)

    assert.equal(reseeded[0].x, 0.6)
    assert.equal(reseeded[0].y, 0.6)
  })

  it('leaves the moved element inside the list, so the guides can skip it by identity', () => {
    const elements = [element('a', { x: 0.4, y: 0.4 }), element('b')]
    const drag = startMoveDrag(elements[0], { x: 0.4, y: 0.4 }, 1)

    const reseeded = reseed(elements)
    const moved = moveDraggedElement(drag, reseeded, { x: 0.6, y: 0.5 }, false)

    assert.ok(reseeded.includes(moved))
  })

  it('snaps to the grid, and places freely when the pointer asks for it', () => {
    const elements = [element('a', { x: 0.4, y: 0.4 })]
    const drag = startMoveDrag(elements[0], { x: 0.4, y: 0.4 }, 1)

    moveDraggedElement(drag, elements, { x: 0.61, y: 0.61 }, false)
    assert.deepEqual({ x: elements[0].x, y: elements[0].y }, { x: 0.6, y: 0.6 })

    moveDraggedElement(drag, elements, { x: 0.61, y: 0.61 }, true)
    assert.deepEqual({ x: elements[0].x, y: elements[0].y }, { x: 0.61, y: 0.61 })
  })

  it('reports nothing to move once the element has been deleted mid drag', () => {
    const elements = [element('a', { x: 0.4, y: 0.4 }), element('b')]
    const drag = startMoveDrag(elements[0], { x: 0.4, y: 0.4 }, 1)

    // Deleted server side, so the reseed comes back without it.
    const reseeded = reseed([elements[1]])

    assert.equal(moveDraggedElement(drag, reseeded, { x: 0.6, y: 0.5 }, false), null)
    assert.deepEqual(reseeded, [element('b')])
  })
})

describe('scaleDraggedElement', () => {
  const CENTRE = { x: 0, y: 0 }

  it('resizes the element the user grabbed after a reseed has replaced it', () => {
    const elements = [element('a', { scale: 1 }), element('b', { scale: 1 })]
    const grabbed = elements[0]
    const drag = startScaleDrag(grabbed, CENTRE, distanceFrom(CENTRE, { x: 100, y: 0 }), 1)

    const reseeded = reseed(elements)
    const resized = scaleDraggedElement(drag, reseeded, { x: 200, y: 0 })

    assert.equal(resized, reseeded[0])
    assert.equal(reseeded[0].scale, 2)
    assert.equal(grabbed.scale, 1)
  })

  it('clamps and steps to what the inspector slider offers', () => {
    const elements = [element('a', { scale: 1 })]
    const drag = startScaleDrag(elements[0], CENTRE, 100, 1)

    scaleDraggedElement(drag, elements, { x: 1000, y: 0 })
    assert.equal(elements[0].scale, 4)

    scaleDraggedElement(drag, elements, { x: 1, y: 0 })
    assert.equal(elements[0].scale, 0.25)

    scaleDraggedElement(drag, elements, { x: 130, y: 0 })
    assert.equal(elements[0].scale, 1.25)
  })

  it('measures against the scale the element had when the grip was grabbed', () => {
    const elements = [element('a', { scale: 2 })]
    const drag = startScaleDrag(elements[0], CENTRE, 100, 1)

    const reseeded = reseed(elements)
    scaleDraggedElement(drag, reseeded, { x: 150, y: 0 })

    assert.equal(reseeded[0].scale, 3)
  })

  it('reports nothing to resize once the element has been deleted mid drag', () => {
    const elements = [element('a'), element('b')]
    const drag = startScaleDrag(elements[0], CENTRE, 100, 1)

    assert.equal(scaleDraggedElement(drag, reseed([elements[1]]), { x: 200, y: 0 }), null)
  })
})

describe('rotateDraggedElement', () => {
  const CENTRE = { x: 0, y: 0 }
  const GRABBED_AT = { x: 100, y: 0 }

  it('turns the element the user grabbed after a reseed has replaced it', () => {
    const elements = [element('a', { rotation: 0 }), element('b', { rotation: 0 })]
    const grabbed = elements[0]
    const drag = startRotateDrag(grabbed, CENTRE, GRABBED_AT, 1)

    const reseeded = reseed(elements)
    const turned = rotateDraggedElement(drag, reseeded, { x: 0, y: 100 }, true)

    assert.equal(turned, reseeded[0])
    assert.equal(reseeded[0].rotation, 90)
    assert.equal(grabbed.rotation, 0)
  })

  it('keeps the grab offset across a reseed, so the icon does not swing to meet the pointer', () => {
    const elements = [element('a', { rotation: 45 })]
    const drag = startRotateDrag(elements[0], CENTRE, GRABBED_AT, 1)

    const reseeded = reseed(elements)
    rotateDraggedElement(drag, reseeded, GRABBED_AT, true)

    assert.equal(reseeded[0].rotation, 45)
  })

  it('snaps to fifteen degrees, and takes whole degrees when the pointer asks for it', () => {
    const elements = [element('a', { rotation: 0 })]
    const drag = startRotateDrag(elements[0], CENTRE, GRABBED_AT, 1)
    const twentyDegrees = { x: Math.cos(Math.PI / 9), y: Math.sin(Math.PI / 9) }

    rotateDraggedElement(drag, elements, twentyDegrees, true)
    assert.equal(elements[0].rotation, 15)

    rotateDraggedElement(drag, elements, twentyDegrees, false)
    assert.equal(elements[0].rotation, 20)
  })

  it('reports nothing to turn once the element has been deleted mid drag', () => {
    const elements = [element('a'), element('b')]
    const drag = startRotateDrag(elements[0], CENTRE, GRABBED_AT, 1)

    assert.equal(rotateDraggedElement(drag, reseed([elements[1]]), { x: 0, y: 100 }, true), null)
  })
})

describe('placeFraction', () => {
  it('lands on the grid by default', () => {
    assert.equal(placeFraction(0.61, false), 0.6)
    assert.equal(placeFraction(0.64, false), 0.65)
  })

  it('places freely when Alt is held', () => {
    assert.equal(placeFraction(0.61, true), 0.61)
  })

  it('keeps a placement inside the stage either way', () => {
    assert.equal(placeFraction(1.4, false), 1)
    assert.equal(placeFraction(-0.3, true), 0)
  })
})

describe('distanceFrom', () => {
  it('measures a pointer against a centre', () => {
    assert.equal(distanceFrom({ x: 0, y: 0 }, { x: 3, y: 4 }), 5)
    assert.equal(distanceFrom({ x: 10, y: 10 }, { x: 10, y: 10 }), 0)
  })
})
