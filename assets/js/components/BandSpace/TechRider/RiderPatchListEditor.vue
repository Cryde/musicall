<template>
  <div class="flex flex-col gap-4">
    <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>

    <!-- Stacked, not side by side. Two grids sharing the width leaves 65px for a name and 85px
         for a routing note that may run to 180 characters, which is not a column, it is an
         ellipsis. Outputs are only ever a handful of rows, so the vertical cost is small. -->
    <div class="flex flex-col gap-8">
      <RiderPatchGrid
        label="Entrées"
        :rows="inputs"
        :max-rows="MAX_ROWS_PER_DIRECTION"
        :errors="errors.inputs"
        :read-only="readOnly"
      />
      <RiderPatchGrid
        label="Retours"
        :rows="outputs"
        :max-rows="MAX_ROWS_PER_DIRECTION"
        :errors="errors.outputs"
        :read-only="readOnly"
      />
    </div>

    <!-- Explicit save, never autosave: a half typed row is a normal state in a grid, and the
         endpoint replaces the whole list, so saving mid-edit would write nonsense. -->
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
        @click="reset"
      />
      <Button label="Sauvegarder" icon="pi pi-check" size="small" :loading="isSaving" @click="save" />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'
import RiderPatchGrid from './RiderPatchGrid.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  riderId: { type: String, required: true },
  itemId: { type: String, required: true },
  /** `{ inputs: [...], outputs: [...] }` as the API returns it, or null on a fresh item. */
  patchList: { type: Object, default: null },
  readOnly: { type: Boolean, default: false }
})

/** Mirrors TechRiderPatchRows::MAX_ROWS_PER_DIRECTION. */
const MAX_ROWS_PER_DIRECTION = 64

const techRidersStore = useBandTechRidersStore()
const toast = useToast()

const inputs = reactive([])
const outputs = reactive([])
const errors = reactive({
  inputs: { list: [], rows: {} },
  outputs: { list: [], rows: {} }
})
const globalError = ref(null)
const isSaving = ref(false)

/**
 * The last state the server confirmed, as a string. Comparing serialised payloads rather than
 * tracking every field means no edit can be missed because somebody forgot to flag it, and a
 * change that cancels itself out correctly reads as clean again.
 */
const savedSnapshot = ref('')
let keyCounter = 0

const isDirty = computed(() => serialise() !== savedSnapshot.value)

function toLocalRows(apiRows) {
  return (apiRows ?? []).map((row) => ({
    // Server ids are regenerated on every save, so they cannot key a list that survives one.
    key: `row-${props.itemId}-${keyCounter++}`,
    channel: row.channel,
    name: row.name ?? '',
    microphone: row.microphone ?? '',
    routing: row.routing ?? '',
    colour: row.colour ?? null
  }))
}

/**
 * Empty strings collapse to null, matching what the server stores, so a field the user typed
 * into and then cleared does not read as a change forever after.
 */
function toPayloadRows(rows) {
  return rows.map((row) => ({
    channel: row.channel,
    name: row.name?.trim() ? row.name.trim() : null,
    microphone: row.microphone?.trim() ? row.microphone.trim() : null,
    routing: row.routing?.trim() ? row.routing.trim() : null,
    colour: row.colour ?? null
  }))
}

function serialise() {
  return JSON.stringify({ inputs: toPayloadRows(inputs), outputs: toPayloadRows(outputs) })
}

function seed() {
  inputs.splice(0, inputs.length, ...toLocalRows(props.patchList?.inputs))
  outputs.splice(0, outputs.length, ...toLocalRows(props.patchList?.outputs))
  clearErrors()
  savedSnapshot.value = serialise()
}

function reset() {
  seed()
}

function clearErrors() {
  errors.inputs = { list: [], rows: {} }
  errors.outputs = { list: [], rows: {} }
  globalError.value = null
}

const FIELD_LABELS = {
  channel: 'Canal',
  name: 'Nom',
  microphone: 'Micro',
  routing: 'Routage',
  colour: 'Couleur'
}

/**
 * Maps the server's property paths back onto rows. A duplicate channel arrives as `inputs`, a
 * bad field as `inputs[3].routing`; both have to land somewhere the user can see, or a refused
 * save looks like the data was lost when in fact the server kept the previous list.
 */
function applyViolations(violations) {
  clearErrors()

  for (const violation of violations) {
    const match = /^(inputs|outputs)(?:\[(\d+)])?(?:\.(\w+))?$/.exec(violation.propertyPath ?? '')
    if (!match) {
      globalError.value = [globalError.value, violation.message].filter(Boolean).join('. ')
      continue
    }

    const [, direction, index, field] = match
    if (index === undefined) {
      errors[direction].list.push(violation.message)
      continue
    }

    const rowIndex = Number(index)
    const existing = errors[direction].rows[rowIndex] ?? []
    errors[direction].rows[rowIndex] = [
      ...existing,
      field ? `${FIELD_LABELS[field] ?? field} : ${violation.message}` : violation.message
    ]
  }
}

/**
 * A blank channel is caught here rather than sent. The server would reject it, but with a
 * message about the row's shape, which does not tell somebody who tabbed past a field what to
 * go and fix.
 */
function findBlankChannel() {
  for (const [direction, rows] of [
    ['inputs', inputs],
    ['outputs', outputs]
  ]) {
    const index = rows.findIndex((row) => row.channel === null || row.channel === undefined)
    if (index !== -1) return { direction, index }
  }

  return null
}

async function save() {
  clearErrors()

  const blank = findBlankChannel()
  if (blank) {
    errors[blank.direction].rows[blank.index] = ['Canal : ce champ est requis']
    return
  }

  // Both captured before the request goes out, and the snapshot is the one applied afterwards.
  // Nothing stops the user typing while the PUT is in flight, and re-serialising after the await
  // would fold those keystrokes into the saved baseline as though the server had confirmed them.
  // The grid would then read as clean, every guard would stand down, and the edit would be lost
  // on the next navigation without a prompt.
  const payload = { inputs: toPayloadRows(inputs), outputs: toPayloadRows(outputs) }
  const sentSnapshot = JSON.stringify(payload)

  isSaving.value = true
  try {
    await techRidersStore.savePatchList(props.bandSpaceId, props.riderId, props.itemId, payload)
    savedSnapshot.value = sentSnapshot
    toast.add({ severity: 'success', summary: 'Patch list enregistrée', life: 2500 })
  } catch (e) {
    if (e.isValidationError) {
      applyViolations(e.violations ?? [])
    } else {
      globalError.value = e.message
    }
  } finally {
    isSaving.value = false
  }
}

// Seeded before the watchers exist, not after. An empty savedSnapshot compares unequal to an
// empty grid, so a watcher created first would report a brand new editor as dirty and the page
// would ask about unsaved changes the moment it loaded.
seed()

// The guard that protects these edits lives two levels up, on the route and on the rider
// switcher, because both destroy this component without asking.
watch(isDirty, (dirty) => techRidersStore.setItemDirty(props.itemId, dirty))

// Server violations are addressed by array index, so adding, deleting or moving a row leaves
// them pointing at whichever row now sits at that index: the red border would move to an
// innocent row and the guilty one would look fine. Editing a field cannot do that, so field
// edits deliberately keep the messages, which is what lets somebody fix one and still see the
// rest.
watch(
  () => [inputs.map((row) => row.key).join(), outputs.map((row) => row.key).join()].join('|'),
  clearErrors
)

// Reseeds when the item is swapped, and when a save elsewhere replaces the rider. Guarded on
// dirtiness so an in-flight edit is never overwritten by a refresh.
watch(
  () => props.patchList,
  () => {
    if (!isDirty.value) seed()
  }
)

watch(() => props.itemId, seed)

// An unmounted editor holds no edits, so leaving the flag set would make the guard warn about
// changes that no longer exist anywhere.
onBeforeUnmount(() => techRidersStore.setItemDirty(props.itemId, false))
</script>
