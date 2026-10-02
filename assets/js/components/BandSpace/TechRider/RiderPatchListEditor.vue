<template>
  <div class="flex flex-col gap-4">
    <Message v-if="globalError" severity="error" :closable="false">{{ globalError }}</Message>

    <!-- Tabs, not stacked: a full input list put the outputs 64 rows down. Both panels stay
         mounted, PrimeVue's default, so a hidden grid keeps its edits and its errors. -->
    <Tabs v-model:value="activeDirection">
      <TabList>
        <Tab v-for="direction in PATCH_DIRECTIONS" :key="direction" :value="direction">
          <span class="flex items-center gap-2">
            <span>{{ PATCH_LIST_DIRECTIONS[direction] }} · {{ rowsOf(direction).length }}</span>
            <template v-if="errorCounts[direction] > 0">
              <i class="pi pi-exclamation-circle text-red-600 dark:text-red-400" aria-hidden="true" />
              <span class="sr-only">, {{ errorLabel(errorCounts[direction]) }}</span>
            </template>
          </span>
        </Tab>
      </TabList>
      <TabPanels class="!px-0">
        <TabPanel v-for="direction in PATCH_DIRECTIONS" :key="direction" :value="direction">
          <RiderPatchGrid
            :label="PATCH_LIST_DIRECTIONS[direction]"
            :rows="rowsOf(direction)"
            :max-rows="MAX_ROWS_PER_DIRECTION"
            :errors="errors[direction]"
            :read-only="readOnly"
            :microphone-suggestions="microphoneSuggestions"
            @cell-left="flush"
          />
        </TabPanel>
      </TabPanels>
    </Tabs>
  </div>
</template>

<script setup>
import Message from 'primevue/message'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import bandSpaceTechRidersApi from '../../../api/bandSpace/band-space-tech-riders.js'
import { useSnapshotAutosave } from '../../../composables/useSnapshotAutosave.js'
import {
  PATCH_LIST_DIRECTIONS,
  PATCH_LIST_LABELS
} from '../../../constants/techRiderPatchColumns.js'
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'
import {
  directionToReveal,
  firstDirectionInError,
  PATCH_DIRECTIONS,
  patchErrorCount
} from '../../../utils/patchListTabs.js'
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

const inputs = reactive([])
const outputs = reactive([])
const errors = reactive({
  inputs: { list: [], rows: {} },
  outputs: { list: [], rows: {} }
})
const globalError = ref(null)
const activeDirection = ref('inputs')
const microphoneSuggestions = ref({ used: [], catalogue: [] })
let keyCounter = 0

function rowsOf(direction) {
  return direction === 'inputs' ? inputs : outputs
}

const errorCounts = computed(() => ({
  inputs: patchErrorCount(errors.inputs),
  outputs: patchErrorCount(errors.outputs)
}))

function errorLabel(count) {
  return count > 1 ? `${count} erreurs` : `${count} erreur`
}

// A held or refused save must not leave its message on the hidden tab. Only when a new direction
// goes wrong, so somebody who opens the other tab on purpose, or edits there while the autosave
// keeps refusing, is not pulled back on every attempt.
watch(
  () => firstDirectionInError(errors),
  () => {
    activeDirection.value =
      directionToReveal(errors, activeDirection.value) ?? activeDirection.value
  }
)

function toLocalRows(apiRows) {
  return (apiRows ?? []).map((row) => ({
    // Server ids are regenerated on every save, so they cannot key a list that survives one.
    key: `row-${props.itemId}-${keyCounter++}`,
    channel: row.channel,
    stereo: row.stereo ?? false,
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
    stereo: row.stereo,
    name: row.name?.trim() ? row.name.trim() : null,
    microphone: row.microphone?.trim() ? row.microphone.trim() : null,
    routing: row.routing?.trim() ? row.routing.trim() : null,
    colour: row.colour ?? null
  }))
}

function serialise() {
  return JSON.stringify({ inputs: toPayloadRows(inputs), outputs: toPayloadRows(outputs) })
}

/** The server's grid in the shape `serialise` produces, to compare without reseeding. */
function serialiseServerGrid() {
  return JSON.stringify({
    inputs: toPayloadRows(toLocalRows(props.patchList?.inputs)),
    outputs: toPayloadRows(toLocalRows(props.patchList?.outputs))
  })
}

function seedRows() {
  inputs.splice(0, inputs.length, ...toLocalRows(props.patchList?.inputs))
  outputs.splice(0, outputs.length, ...toLocalRows(props.patchList?.outputs))
  clearErrors()
}

function clearErrors() {
  errors.inputs = { list: [], rows: {} }
  errors.outputs = { list: [], rows: {} }
  globalError.value = null
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
      field ? `${PATCH_LIST_LABELS[field] ?? field} : ${violation.message}` : violation.message
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

/** Holds a save the server would refuse with a message about the row's shape, and says why. */
function validate() {
  const blank = findBlankChannel()
  if (!blank) return null

  const message = `${PATCH_LIST_LABELS.channel} : ce champ est requis`
  errors[blank.direction].rows[blank.index] = [message]

  return message
}

// Seeded before autosave takes its first snapshot, so a freshly opened grid reads as saved.
seedRows()

const { markSaved, shouldReseed, flush } = useSnapshotAutosave({
  itemId: () => props.itemId,
  isReadOnly: () => props.readOnly,
  serialise,
  validate,
  // Cleared once the save is taken, not before it is sent: clearing first would make every refusal
  // look like a new error and drag the editor back to its tab each time.
  save: async (payload) => {
    await techRidersStore.savePatchList(props.bandSpaceId, props.riderId, props.itemId, payload)
    clearErrors()
  },
  onError: (e) => {
    if (e.isValidationError) {
      applyViolations(e.violations ?? [])
      return e.violations?.[0]?.message ?? e.message
    }
    globalError.value = e.message
    return e.message
  }
})

function seed() {
  seedRows()
  markSaved()
}

// Server violations are addressed by array index, so adding, deleting or moving a row leaves
// them pointing at whichever row now sits at that index: the red border would move to an
// innocent row and the guilty one would look fine. Editing a field cannot do that, so field
// edits deliberately keep the messages, which is what lets somebody fix one and still see the
// rest.
watch(
  () => [inputs.map((row) => row.key).join(), outputs.map((row) => row.key).join()].join('|'),
  clearErrors
)

// Reseeds when the item is swapped, and when a save elsewhere replaces the rider. Never over an
// edit still on its way, and never for the answer to our own save, which would rebuild every row
// and take the cursor away mid-word.
watch(
  () => props.patchList,
  () => {
    if (shouldReseed(serialiseServerGrid())) seed()
  }
)

watch(
  () => props.itemId,
  () => {
    seed()
    activeDirection.value = 'inputs'
  }
)

// Suggestions only help: without them the cell is a plain text field, so a failure stays quiet.
onMounted(async () => {
  if (props.readOnly) return
  try {
    microphoneSuggestions.value = await bandSpaceTechRidersApi.getMicrophoneSuggestions(
      props.bandSpaceId
    )
  } catch {
    // Typed freely instead.
  }
})
</script>
