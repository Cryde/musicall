<template>
  <div class="flex flex-col gap-4">
    <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>
    <!-- Retryable, because without the catalogue there is nothing to place: a transient failure
         would otherwise leave the editor useless until the whole page is reloaded. -->
    <Message v-if="loadError" severity="error" :closable="false">
      <div class="flex flex-wrap items-center justify-between gap-3 w-full">
        <span>{{ loadError }}</span>
        <Button
          label="Réessayer"
          icon="pi pi-refresh"
          size="small"
          :loading="isLoadingIcons"
          @click="loadIcons"
        />
      </div>
    </Message>

    <div v-if="isLoadingIcons" class="py-8 flex justify-center">
      <ProgressSpinner style="width: 2rem; height: 2rem" />
    </div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_16rem] gap-4">
      <div class="flex flex-col gap-3 min-w-0">
        <div class="flex flex-wrap items-center gap-3">
          <span class="text-xs text-surface-600 dark:text-surface-300">
            {{ elements.length }} / {{ MAX_ELEMENTS }} éléments
          </span>
          <span class="flex-1" />
          <label :for="`${uid}-ratio`" class="text-xs">Format de scène</label>
          <Select
            :id="`${uid}-ratio`"
            :model-value="aspectRatio"
            :options="ASPECT_RATIO_OPTIONS"
            option-label="label"
            option-value="value"
            :disabled="readOnly"
            size="small"
            class="w-40"
            @update:model-value="setAspectRatio"
          />
        </div>

        <StagePlotCanvas
          ref="canvasRef"
          :elements="elements"
          :aspect-ratio="aspectRatio"
          :icons="icons"
          :selected-id="selectedId"
          :read-only="readOnly"
          @select="(id) => (selectedId = id)"
          @place="placeAt"
          @edit-label="focusLabelFor"
          @delete="removeElement"
        />

        <!-- The plot in words. A drawing conveys nothing to a screen reader, so the same
             information exists as text rather than being locked inside the picture. -->
        <details class="text-xs">
          <summary class="cursor-pointer text-surface-600 dark:text-surface-300">
            Description textuelle du plan ({{ elements.length }})
          </summary>
          <ul v-if="elements.length > 0" class="mt-2 flex flex-col gap-0.5">
            <li v-for="element in elements" :key="element.id">{{ describeElement(element) }}</li>
          </ul>
          <p v-else class="mt-2 text-surface-600 dark:text-surface-300">La scène est vide.</p>
        </details>
      </div>

      <div class="flex flex-col gap-4 min-w-0">
        <StagePlotIconPicker :icons="icons" :read-only="readOnly" @place="placeInMiddle" />
        <StagePlotElementInspector
          :element="selectedElement"
          :icons="icons"
          :read-only="readOnly"
          @update="updateElement"
          @duplicate="duplicateElement"
          @delete="removeElement"
        />
        <StagePlotLegendEditor
          :legend="legend"
          :icons="icons"
          :read-only="readOnly"
          @add="addLegendEntry"
          @update="updateLegendEntry"
          @remove="removeLegendEntry"
        />
      </div>
    </div>

    <!-- Explicit save. A drag crosses dozens of pixels, and autosaving would be a request per
         one of them; the sections tab autosaves because typing has natural pauses. -->
    <div
      v-if="!readOnly && isDirty"
      class="sticky bottom-0 flex flex-wrap items-center gap-3 p-3 rounded-lg border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950"
    >
      <i class="pi pi-exclamation-circle text-primary" aria-hidden="true" />
      <span class="text-sm">Modifications non enregistrées.</span>
      <span class="flex-1" />
      <Button
        label="Annuler les modifications"
        severity="secondary"
        text
        size="small"
        :disabled="isSaving"
        @click="seed"
      />
      <Button label="Sauvegarder" icon="pi pi-check" size="small" :loading="isSaving" @click="save" />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Select from 'primevue/select'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, reactive, ref, useId, watch } from 'vue'
import {
  DEFAULT_ASPECT_RATIO,
  describePosition,
  MAX_ELEMENTS,
  STAGE_PLOT_SCHEMA_VERSION,
  snapFraction
} from '../../../constants/stagePlot.js'
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'
import StagePlotCanvas from './StagePlot/StagePlotCanvas.vue'
import StagePlotElementInspector from './StagePlot/StagePlotElementInspector.vue'
import StagePlotIconPicker from './StagePlot/StagePlotIconPicker.vue'
import StagePlotLegendEditor from './StagePlot/StagePlotLegendEditor.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  riderId: { type: String, required: true },
  itemId: { type: String, required: true },
  /** The stored plot document, which is the item's `content`, or null on a fresh item. */
  content: { type: Object, default: null },
  readOnly: { type: Boolean, default: false }
})

const ASPECT_RATIO_OPTIONS = [
  { value: 1.0, label: 'Carrée (1:1)' },
  { value: 1.4, label: 'Standard (1.4:1)' },
  { value: 1.78, label: 'Large (16:9)' },
  { value: 2.4, label: 'Très large (2.4:1)' }
]

const techRidersStore = useBandTechRidersStore()
const toast = useToast()

const uid = useId()
const canvasRef = ref(null)

const elements = reactive([])
const legend = reactive([])
const aspectRatio = ref(DEFAULT_ASPECT_RATIO)
const selectedId = ref(null)

const isSaving = ref(false)
const isLoadingIcons = ref(true)
const globalError = ref(null)
const loadError = ref(null)

/**
 * The last state the server confirmed, serialised. Comparing whole documents means no edit can be
 * missed because somebody forgot to flag it, and a change that cancels itself out reads as clean.
 */
const savedSnapshot = ref('')
let elementCounter = 0

const icons = computed(() => techRidersStore.stagePlotIcons)
const isDirty = computed(() => serialise() !== savedSnapshot.value)
const selectedElement = computed(
  () => elements.find((element) => element.id === selectedId.value) ?? null
)

/**
 * The document as it will be stored. Keys are snake_case and coordinates are fractions, matching
 * what the validator accepts; `undefined` never appears because JSON would drop it silently.
 */
function toPlot() {
  return {
    version: STAGE_PLOT_SCHEMA_VERSION,
    stage: { aspect_ratio: aspectRatio.value },
    elements: elements.map((element) => ({
      id: element.id,
      icon: element.icon,
      x: element.x,
      y: element.y,
      scale: element.scale ?? 1,
      rotation: element.rotation ?? 0,
      label: element.label?.trim() ? element.label.trim() : null,
      colour: element.colour ?? null
    })),
    legend: legend.map((entry) => ({
      icon: entry.icon,
      label: entry.label?.trim() ? entry.label.trim() : null
    }))
  }
}

function serialise() {
  return JSON.stringify(toPlot())
}

function seed() {
  const plot = props.content ?? {}
  elements.splice(
    0,
    elements.length,
    ...(plot.elements ?? []).map((element) => ({
      id: element.id,
      icon: element.icon,
      x: element.x,
      y: element.y,
      scale: element.scale ?? 1,
      rotation: element.rotation ?? 0,
      label: element.label ?? '',
      colour: element.colour ?? null
    }))
  )
  legend.splice(0, legend.length, ...(plot.legend ?? []).map((entry) => ({ ...entry })))
  aspectRatio.value = plot.stage?.aspect_ratio ?? DEFAULT_ASPECT_RATIO

  // The selection survives a reseed when its element does. Clearing it unconditionally desynced
  // selection from focus: the canvas only selects on a focus *event*, so an element that keeps
  // focus across a cancel never re-emits, and the user ends up focused on an element whose
  // inspector is empty and whose handles have vanished. Swapping items resolves to null on its
  // own here, because none of the new ids match.
  selectedId.value = elements.some((element) => element.id === selectedId.value)
    ? selectedId.value
    : null
  globalError.value = null
  savedSnapshot.value = serialise()
}

function describeElement(element) {
  const name =
    element.label || icons.value.find((icon) => icon.slug === element.icon)?.label || element.icon

  return `${name}, ${describePosition(element.x, element.y)}`
}

/**
 * Ids are generated client side and stored, unlike the patch list's, because the plot correlates
 * an element with the selection and with the legend. Prefixed with the item so two editors open at
 * once cannot mint the same one.
 */
function nextElementId() {
  return `${props.itemId.slice(0, 8)}-${Date.now().toString(36)}-${elementCounter++}`
}

function addElement(slug, x, y) {
  if (elements.length >= MAX_ELEMENTS) {
    globalError.value = `Un plan de scène ne peut pas dépasser ${MAX_ELEMENTS} éléments.`

    return null
  }

  const element = {
    id: nextElementId(),
    icon: slug,
    x,
    y,
    scale: 1,
    rotation: 0,
    // Defaulted to the icon's French name so an unlabelled plot still reads, rather than a
    // placeholder that says nothing on a printed page.
    label: icons.value.find((icon) => icon.slug === slug)?.label ?? '',
    colour: null
  }
  elements.push(element)
  selectedId.value = element.id
  globalError.value = null

  return element
}

function placeAt({ slug, x, y }) {
  const element = addElement(slug, x, y)
  if (element) focusElement(element.id)
}

/**
 * The keyboard placement path: the middle of the stage, then move it with the arrows. Landing in a
 * fixed spot is deliberate, because a pointerless user has no cursor position to place at.
 */
function placeInMiddle(slug) {
  const element = addElement(slug, snapFraction(0.5), snapFraction(0.5))
  if (element) focusElement(element.id)
}

function focusElement(id) {
  // Focus moves to the new element so the arrows act on it immediately, with no click needed.
  requestAnimationFrame(() => canvasRef.value?.focusElement(id))
}

function updateElement({ id, ...patch }) {
  const element = elements.find((candidate) => candidate.id === id)
  if (element) Object.assign(element, patch)
}

function duplicateElement(id) {
  const source = elements.find((element) => element.id === id)
  if (!source) return

  // Offset, so the copy is visible instead of sitting exactly on top of the original.
  const copy = addElement(source.icon, snapFraction(source.x + 0.05), snapFraction(source.y + 0.05))
  if (!copy) return

  Object.assign(copy, {
    scale: source.scale,
    rotation: source.rotation,
    label: source.label,
    colour: source.colour
  })
  focusElement(copy.id)
}

function removeElement(id) {
  const index = elements.findIndex((element) => element.id === id)
  if (index === -1) return

  elements.splice(index, 1)
  if (selectedId.value === id) selectedId.value = null
}

/** A double click or Enter on the canvas sends the user to the label field rather than editing inline. */
function focusLabelFor(id) {
  selectedId.value = id
  requestAnimationFrame(() => {
    document.getElementById(`${uid}-label`)?.focus()
  })
}

function setAspectRatio(value) {
  aspectRatio.value = value
}

function addLegendEntry(entry) {
  legend.push(entry)
}

function updateLegendEntry({ index, label }) {
  if (legend[index]) legend[index].label = label
}

function removeLegendEntry(index) {
  legend.splice(index, 1)
}

async function save() {
  globalError.value = null

  // Captured before the request, and applied unchanged afterwards. Re-serialising after the await
  // would fold anything dragged during the round trip into the saved baseline as though the server
  // had confirmed it, and every unsaved-changes guard would then stand down.
  const plot = toPlot()
  const sentSnapshot = JSON.stringify(plot)

  isSaving.value = true
  try {
    await techRidersStore.saveStagePlot(props.bandSpaceId, props.riderId, props.itemId, plot)
    savedSnapshot.value = sentSnapshot
    toast.add({ severity: 'success', summary: 'Plan de scène enregistré', life: 2500 })
  } catch (e) {
    // The server's own message, so a rule the editor does not mirror still reaches the user.
    globalError.value = e.isValidationError
      ? (e.violations ?? []).map((violation) => violation.message).join('. ')
      : e.message
  } finally {
    isSaving.value = false
  }
}

async function loadIcons() {
  isLoadingIcons.value = true
  loadError.value = null
  try {
    await techRidersStore.loadStagePlotIcons()
  } catch (e) {
    loadError.value = `Les icônes n'ont pas pu être chargées : ${e.message}`
  } finally {
    isLoadingIcons.value = false
  }
}

// Seeded before the watchers exist: an empty snapshot compares unequal to an empty document, so a
// watcher created first would report a brand new editor as dirty and the page would ask about
// unsaved changes the moment it loaded.
seed()

watch(isDirty, (dirty) => techRidersStore.setItemDirty(props.itemId, dirty))

// Reseeds when a save elsewhere replaces the rider, guarded on dirtiness so an in-flight edit is
// never overwritten by a refresh.
watch(
  () => props.content,
  () => {
    if (!isDirty.value) seed()
  }
)

watch(() => props.itemId, seed)

// An unmounted editor holds no edits, so leaving the flag set would make the guard warn about
// changes that no longer exist anywhere.
onBeforeUnmount(() => techRidersStore.setItemDirty(props.itemId, false))

loadIcons()
</script>
