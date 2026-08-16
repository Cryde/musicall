<template>
  <div class="flex flex-col gap-1 min-w-0">
    <!-- The stage box. Aspect ratio drives the height so the shape is the same at any width, which
         is what makes the stored fractions mean the same thing on a phone and on an A4 page. -->
    <div
      ref="stageRef"
      class="relative w-full rounded-lg border-2 border-surface-300 dark:border-surface-600 bg-surface-50 dark:bg-surface-900 overflow-hidden select-none"
      :style="{ aspectRatio: String(aspectRatio), backgroundImage: gridImage, backgroundSize: gridSize }"
      role="application"
      :aria-label="`Plan de scène, ${elements.length} éléments. Utilisez Tab pour parcourir les éléments et les flèches pour les déplacer.`"
      @pointerdown="handleStagePointerDown"
      @dragover.prevent
      @drop.prevent="handleDrop"
    >
      <!-- The wrapper is sized in stage percentages, and the icon fills it. Sizing the icon itself
           in percent does not work: its containing block is this wrapper, not the stage, so the
           percentage resolved against a shrink-to-fit width and left the box as wide as the label
           with a collapsed icon inside it. -->
      <div
        v-for="element in elements"
        :key="element.id"
        :ref="(el) => registerElement(element.id, el)"
        class="absolute -translate-x-1/2 -translate-y-1/2 rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
        :class="[
          element.id === selectedId ? 'ring-2 ring-primary-500' : '',
          readOnly ? '' : 'cursor-move'
        ]"
        :style="{
          left: `${element.x * 100}%`,
          top: `${element.y * 100}%`,
          width: `${BASE_ICON_PERCENT * (element.scale ?? 1)}%`,
          minWidth: '20px'
        }"
        :tabindex="readOnly ? -1 : 0"
        role="button"
        :aria-label="describeElement(element)"
        :aria-pressed="element.id === selectedId"
        @pointerdown.stop="handleElementPointerDown($event, element)"
        @keydown="handleElementKeydown($event, element)"
        @focus="emit('select', element.id)"
        @dblclick.stop="emit('edit-label', element.id)"
      >
        <StagePlotIcon
          :slug="element.icon"
          :image-url="iconImage(element.icon)"
          :colour="symbolColour(element)"
          class="pointer-events-none mx-auto"
          :style="{
            transform: `rotate(${element.rotation ?? 0}deg)`,
            width: `${SYMBOL_SIZE_PERCENT}%`
          }"
        />

        <!-- Real text, not painted into a bitmap: selectable, sharp at any size, and the thing
             that carries the meaning when colour cannot. Taken out of the flow so a long label
             cannot widen the box, which is what the selection ring wraps. -->
        <span
          v-if="element.label"
          class="absolute top-full left-1/2 -translate-x-1/2 mt-0.5 text-[10px] leading-tight px-1 rounded bg-surface-0/80 dark:bg-surface-950/80 whitespace-nowrap pointer-events-none"
          :style="element.colour ? { color: colourHex(element.colour) } : undefined"
        >
          {{ element.label }}
        </span>

        <!-- Handles on the selected element only, so the stage is not covered in furniture. Both
             duplicate a control in the inspector rather than replacing it: this is the direct
             manipulation shortcut, the panel is the accessible and precise path. -->
        <template v-if="!readOnly && element.id === selectedId">
          <button
            type="button"
            class="absolute -top-2 -right-2 w-4 h-4 flex items-center justify-center rounded-full bg-primary text-primary-contrast shadow focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500"
            :aria-label="`Faire pivoter ${describeElement(element)}, actuellement ${element.rotation ?? 0} degrés`"
            v-tooltip.top="'Aligner sur le prochain quart de tour'"
            @pointerdown.stop
            @click.stop="rotateElement(element)"
          >
            <i class="pi pi-refresh text-[8px]" aria-hidden="true" />
          </button>

          <!-- A drag grip, so aria-hidden: a pointer-only gesture has no keyboard meaning, and the
               inspector's slider is the equivalent that does. -->
          <span
            class="absolute -bottom-1.5 -right-1.5 w-3 h-3 rounded-sm bg-primary border border-surface-0 dark:border-surface-950 cursor-nwse-resize"
            aria-hidden="true"
            @pointerdown.stop="handleScalePointerDown($event, element)"
          />

          <!-- Free rotation, at top-left because the right edge is already taken: at the smallest
               scale the wrapper is only 20px, and the rotate button and the scale grip between them
               cover the whole of it. aria-hidden for the same reason as the scale grip. -->
          <span
            class="absolute -top-1.5 -left-1.5 w-3 h-3 rounded-full bg-primary border border-surface-0 dark:border-surface-950 cursor-grab"
            aria-hidden="true"
            @pointerdown.stop="handleRotatePointerDown($event, element)"
          />
        </template>
      </div>

      <!-- Alignment guides, drawn after the elements so they sit over the icons. They only inform:
           the grid is what keeps a plot tidy, and a line that also pulled the element would fight it.
           aria-hidden and pointer-events-none for the same reason as the drag grips, a purely visual
           aid during a pointer gesture. -->
      <div
        v-if="alignmentGuides.x !== null"
        class="absolute top-0 bottom-0 w-px bg-primary-500/70 pointer-events-none"
        :style="{ left: `${alignmentGuides.x * 100}%` }"
        aria-hidden="true"
      />
      <div
        v-if="alignmentGuides.y !== null"
        class="absolute left-0 right-0 h-px bg-primary-500/70 pointer-events-none"
        :style="{ top: `${alignmentGuides.y * 100}%` }"
        aria-hidden="true"
      />

      <!-- The audience side. Everything on a rider is described relative to it, so an unlabelled
           box is ambiguous. -->
      <div
        class="absolute inset-x-0 bottom-0 h-5 flex items-center justify-center bg-surface-200/80 dark:bg-surface-800/80 text-[10px] uppercase tracking-wider text-surface-700 dark:text-surface-200"
      >
        Face public
      </div>
    </div>

    <p class="text-xs text-surface-600 dark:text-surface-300">
      Glissez une icône depuis la liste, ou sélectionnez-la et appuyez sur Entrée. Flèches pour
      déplacer, Maj + flèches pour aller plus vite, Alt + flèches pour un réglage fin, Alt pendant
      un déplacement à la souris pour ignorer la grille, Suppr pour retirer. Sur l'élément
      sélectionné, la poignée en bas à droite règle la taille, celle en haut à gauche l'orientation
      (par pas de 15°, ou Alt pour un angle libre) et le bouton en haut à droite l'aligne sur le
      prochain quart de tour.
    </p>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import {
  BASE_ICON_PERCENT,
  COARSE_NUDGE_STEP,
  describePosition,
  FINE_NUDGE_STEP,
  findAlignmentGuides,
  NUDGE_STEP,
  nextQuarterTurn,
  SNAP_STEP,
  SYMBOL_SIZE_PERCENT,
  snapFraction,
  toFraction
} from '../../../../constants/stagePlot.js'
import { TECH_RIDER_COLOURS } from '../../../../constants/techRiderColours.js'
import {
  distanceFrom,
  moveDraggedElement,
  placeFraction,
  rotateDraggedElement,
  scaleDraggedElement,
  startMoveDrag,
  startRotateDrag,
  startScaleDrag
} from '../../../../utils/stagePlotDrag.js'
import StagePlotIcon from './StagePlotIcon.vue'

const props = defineProps({
  /** Mutated in place: the parent owns the plot so it can diff the whole document at once. */
  elements: { type: Array, required: true },
  aspectRatio: { type: Number, required: true },
  /** The catalogue, for resolving an icon slug to its image and French label. */
  icons: { type: Array, default: () => [] },
  selectedId: { type: String, default: null },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'place', 'edit-label', 'delete'])

const stageRef = ref(null)
const elementRefs = new Map()

let drag = null
let scaleDrag = null
let rotateDrag = null

/** Frozen because the same reference is assigned on every drag end; nothing may mutate it. */
const NO_GUIDES = Object.freeze({ x: null, y: null })

/** Only ever set while a move drag is running; scaling and rotating do not move an element. */
const alignmentGuides = ref(NO_GUIDES)

const gridImage = computed(() => {
  // Drawn with a gradient rather than an SVG asset: it is two lines, and this way it inherits the
  // theme's border colour instead of shipping a light and a dark image.
  const line = 'color-mix(in srgb, currentColor 12%, transparent)'
  return `linear-gradient(to right, ${line} 1px, transparent 1px), linear-gradient(to bottom, ${line} 1px, transparent 1px)`
})

const gridSize = computed(() => `${SNAP_STEP * 4 * 100}% ${SNAP_STEP * 4 * 100}%`)

function registerElement(id, el) {
  if (el) {
    elementRefs.set(id, el)
  } else {
    elementRefs.delete(id)
  }
}

function iconFor(slug) {
  return props.icons.find((icon) => icon.slug === slug) ?? null
}

function iconImage(slug) {
  return iconFor(slug)?.image_url ?? ''
}

/** The element's own colour wins; otherwise the symbol takes its category's. */
function symbolColour(element) {
  return colourHex(element.colour) ?? iconFor(element.icon)?.category_colour ?? null
}

function colourHex(value) {
  return TECH_RIDER_COLOURS.find((colour) => colour.value === value)?.hex
}

/**
 * What a screen reader hears. The position is words rather than numbers because "0.42, 0.55" says
 * nothing, and this is the only description of the drawing a non-sighted user gets.
 */
function describeElement(element) {
  const name = element.label || iconFor(element.icon)?.label || element.icon
  return `${name}, ${describePosition(element.x, element.y)}`
}

/** Where a pointer sits inside the stage, as fractions. */
function stageFractions(event) {
  const box = stageRef.value.getBoundingClientRect()
  return {
    x: toFraction((event.clientX - box.left) / box.width),
    y: toFraction((event.clientY - box.top) / box.height)
  }
}

function handleStagePointerDown() {
  if (props.readOnly) return
  emit('select', null)
}

function handleElementPointerDown(event, element) {
  if (props.readOnly || event.button !== 0) return
  // One drag at a time. A second pointer starting on another element would overwrite this state,
  // freezing the first element and leaving its stage listeners attached but inert.
  if (drag || scaleDrag || rotateDrag) return

  emit('select', element.id)
  drag = startMoveDrag(element, stageFractions(event), event.pointerId)

  // Captured on the stage, so a fast drag that outruns the pointer keeps moving the element
  // instead of dropping it the moment the cursor leaves the icon.
  stageRef.value.setPointerCapture(event.pointerId)
  stageRef.value.addEventListener('pointermove', handlePointerMove)
  stageRef.value.addEventListener('pointerup', handlePointerUp)
  stageRef.value.addEventListener('pointercancel', handlePointerUp)
}

function endMoveDrag(pointerId) {
  drag = null
  alignmentGuides.value = NO_GUIDES

  stageRef.value.releasePointerCapture(pointerId)
  stageRef.value.removeEventListener('pointermove', handlePointerMove)
  stageRef.value.removeEventListener('pointerup', handlePointerUp)
  stageRef.value.removeEventListener('pointercancel', handlePointerUp)
}

function handlePointerMove(event) {
  if (!drag || event.pointerId !== drag.pointerId) return

  const moved = moveDraggedElement(drag, props.elements, stageFractions(event), event.altKey)
  // The element left the list under the gesture, a reseed dropping it or a delete elsewhere. There
  // is nothing to move, so the gesture ends here rather than throwing on every further move.
  if (!moved) {
    endMoveDrag(event.pointerId)

    return
  }

  alignmentGuides.value = findAlignmentGuides(moved, props.elements)
}

function handlePointerUp(event) {
  if (!drag || event.pointerId !== drag.pointerId) return

  const id = drag.elementId
  endMoveDrag(event.pointerId)

  // No change event to emit: the parent's dirty state is computed from the same reactive elements
  // this mutates, so it already knows. Focus follows the drag so the arrows act on what moved.
  elementRefs.get(id)?.focus()
}

/**
 * The shortcut button: on to the next quarter turn, from wherever the element currently sits.
 *
 * It used to index into ROTATIONS, which returned -1 for anything off the list and so reset the
 * angle to 0. Harmless while nothing could produce an off-list angle; wrong now that the grip can.
 */
function rotateElement(element) {
  element.rotation = nextQuarterTurn(element.rotation ?? 0)
}

/**
 * Rotation by drag: the element follows the pointer's bearing around its own centre.
 *
 * The offset between that bearing and the current angle is taken once, so grabbing the grip does not
 * swing the icon round to meet the pointer. Snapping to 15 degrees keeps a plot tidy, and Alt frees
 * it to whole degrees, which is the same bargain Alt already makes with the position grid.
 */
function handleRotatePointerDown(event, element) {
  if (props.readOnly || drag || scaleDrag || rotateDrag) return

  const box = stageRef.value.getBoundingClientRect()
  const centre = {
    x: box.left + box.width * element.x,
    y: box.top + box.height * element.y
  }
  const point = { x: event.clientX, y: event.clientY }

  rotateDrag = startRotateDrag(element, centre, point, event.pointerId)

  stageRef.value.setPointerCapture(event.pointerId)
  stageRef.value.addEventListener('pointermove', handleRotatePointerMove)
  stageRef.value.addEventListener('pointerup', handleRotatePointerUp)
  stageRef.value.addEventListener('pointercancel', handleRotatePointerUp)
}

function endRotateDrag(pointerId) {
  rotateDrag = null

  stageRef.value.releasePointerCapture(pointerId)
  stageRef.value.removeEventListener('pointermove', handleRotatePointerMove)
  stageRef.value.removeEventListener('pointerup', handleRotatePointerUp)
  stageRef.value.removeEventListener('pointercancel', handleRotatePointerUp)
}

function handleRotatePointerMove(event) {
  if (!rotateDrag || event.pointerId !== rotateDrag.pointerId) return

  const point = { x: event.clientX, y: event.clientY }
  const turned = rotateDraggedElement(rotateDrag, props.elements, point, !event.altKey)
  if (!turned) endRotateDrag(event.pointerId)
}

function handleRotatePointerUp(event) {
  if (!rotateDrag || event.pointerId !== rotateDrag.pointerId) return

  const id = rotateDrag.elementId
  endRotateDrag(event.pointerId)

  elementRefs.get(id)?.focus()
}

/**
 * Scaling by drag, measured as the distance from the element's centre against where the grip
 * started. Absolute ratio rather than incremental deltas, so the size never drifts away from the
 * pointer over a long drag.
 */
function handleScalePointerDown(event, element) {
  if (props.readOnly || drag || scaleDrag || rotateDrag) return

  const box = stageRef.value.getBoundingClientRect()
  const centre = {
    x: box.left + box.width * element.x,
    y: box.top + box.height * element.y
  }
  const startDistance = distanceFrom(centre, { x: event.clientX, y: event.clientY })
  if (startDistance < 1) return

  scaleDrag = startScaleDrag(element, centre, startDistance, event.pointerId)

  stageRef.value.setPointerCapture(event.pointerId)
  stageRef.value.addEventListener('pointermove', handleScalePointerMove)
  stageRef.value.addEventListener('pointerup', handleScalePointerUp)
  stageRef.value.addEventListener('pointercancel', handleScalePointerUp)
}

function endScaleDrag(pointerId) {
  scaleDrag = null

  stageRef.value.releasePointerCapture(pointerId)
  stageRef.value.removeEventListener('pointermove', handleScalePointerMove)
  stageRef.value.removeEventListener('pointerup', handleScalePointerUp)
  stageRef.value.removeEventListener('pointercancel', handleScalePointerUp)
}

function handleScalePointerMove(event) {
  if (!scaleDrag || event.pointerId !== scaleDrag.pointerId) return

  const point = { x: event.clientX, y: event.clientY }
  const resized = scaleDraggedElement(scaleDrag, props.elements, point)
  if (!resized) endScaleDrag(event.pointerId)
}

function handleScalePointerUp(event) {
  if (!scaleDrag || event.pointerId !== scaleDrag.pointerId) return

  const id = scaleDrag.elementId
  endScaleDrag(event.pointerId)

  elementRefs.get(id)?.focus()
}

/** Drop target for the picker's drag placement. */
function handleDrop(event) {
  if (props.readOnly) return

  const slug = event.dataTransfer?.getData('text/plain')
  if (!slug) return

  const position = stageFractions(event)
  emit('place', {
    slug,
    x: placeFraction(position.x, event.altKey),
    y: placeFraction(position.y, event.altKey)
  })
}

/**
 * The keyboard half of every pointer gesture. A canvas that only responds to dragging is unusable
 * without a mouse, which #688 treated as a defect elsewhere in the app.
 */
function handleElementKeydown(event, element) {
  if (props.readOnly) return

  if (event.key === 'Delete' || event.key === 'Backspace') {
    event.preventDefault()
    emit('delete', element.id)

    return
  }

  if (event.key === 'Enter') {
    event.preventDefault()
    emit('edit-label', element.id)

    return
  }

  // Alt for fine, Shift for coarse, plain for one grid step: the same three modes the pointer has.
  const step = event.altKey ? FINE_NUDGE_STEP : event.shiftKey ? COARSE_NUDGE_STEP : NUDGE_STEP
  const moves = {
    ArrowLeft: [-step, 0],
    ArrowRight: [step, 0],
    ArrowUp: [0, -step],
    ArrowDown: [0, step]
  }
  const move = moves[event.key]
  if (!move) return

  event.preventDefault()
  // Re-snapped on the grid steps, so repeated presses cannot accumulate a fractional offset that
  // leaves the element permanently a hair off the line.
  const place = event.altKey ? toFraction : snapFraction
  element.x = place(element.x + move[0])
  element.y = place(element.y + move[1])
}

defineExpose({
  /** Lets the parent put focus on an element it just created, so arrows work immediately. */
  focusElement(id) {
    elementRefs.get(id)?.focus()
  }
})
</script>
