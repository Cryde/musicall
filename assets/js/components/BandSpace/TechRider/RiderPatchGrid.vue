<template>
  <section class="flex flex-col gap-2 min-w-0" :aria-label="label">
    <div class="flex flex-wrap items-center gap-2">
      <h4 class="font-semibold">{{ label }}</h4>
      <span
        class="text-xs px-2 py-0.5 rounded bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-200"
        :class="isFull ? 'text-red-700 dark:text-red-300' : ''"
      >
        {{ rows.length }} / {{ maxRows }}
      </span>
      <span class="flex-1" />

      <template v-if="!readOnly">
        <Button
          label="Renuméroter"
          icon="pi pi-sort-numeric-down"
          severity="secondary"
          text
          size="small"
          :disabled="rows.length === 0"
          v-tooltip.top="'Renumérote les canaux de 1 à N dans l\'ordre affiché'"
          :aria-label="`Renuméroter les canaux (${label.toLowerCase()})`"
          @click="renumber"
        />
        <Button
          label="Colorer une plage"
          icon="pi pi-palette"
          severity="secondary"
          text
          size="small"
          :disabled="rows.length === 0"
          :aria-label="`Colorer une plage de lignes (${label.toLowerCase()})`"
          @click="openRangeDialog"
        />
        <Button
          label="Ajouter"
          icon="pi pi-plus"
          severity="secondary"
          outlined
          size="small"
          :disabled="isFull"
          :aria-label="`Ajouter une ligne (${label.toLowerCase()})`"
          @click="addRow"
        />
      </template>
    </div>

    <Message v-if="isFull" severity="warn" :closable="false" size="small">
      La limite de {{ maxRows }} lignes est atteinte.
    </Message>

    <Message v-for="(message, index) in listErrors" :key="index" severity="error" :closable="false" size="small">
      {{ message }}
    </Message>

    <p v-if="rows.length === 0" class="text-sm text-surface-600 dark:text-surface-300 py-3">
      Aucune ligne. Ajoutez la première entrée de votre patch.
    </p>

    <template v-else>
      <!-- Headers name the columns once instead of every field repeating its label. Each input
           still carries an aria-label with its row, because a header is not associated with a
           cell the way a real <th> would be, and 24 unlabelled inputs are unusable by ear. -->
      <div
        class="hidden sm:grid gap-2 px-2 text-xs font-medium text-surface-600 dark:text-surface-300"
        :style="{ gridTemplateColumns: GRID_COLUMNS }"
        aria-hidden="true"
      >
        <span>Canal</span>
        <span>Nom</span>
        <span>Micro</span>
        <span>Routage</span>
        <span>Couleur</span>
        <span />
      </div>

      <ul class="flex flex-col gap-2">
        <li
          v-for="(row, index) in rows"
          :key="row.key"
          class="rounded-lg border p-2 sm:grid sm:items-center flex flex-col gap-2"
          :style="{ gridTemplateColumns: GRID_COLUMNS }"
          :class="[
            dropTargetKey === row.key ? 'ring-2 ring-primary-400' : '',
            rowHasError(index)
              ? 'border-red-400 dark:border-red-500 bg-red-50 dark:bg-red-950/30'
              : 'border-surface-200 dark:border-surface-700'
          ]"
          :draggable="!readOnly"
          @dragstart="handleDragStart(row.key)"
          @dragend="handleDragEnd"
          @dragover.prevent="handleDragOver(row.key)"
          @dragleave="handleDragLeave(row.key)"
          @drop.prevent="handleDrop(row.key)"
        >
          <!-- The `sm:hidden` captions are the stacked layout's labels. Below sm the column
               headers are gone, so without them a sighted user sees five unlabelled boxes;
               above sm the header already says it and repeating it would be noise. -->
          <div class="flex items-center gap-1">
            <i
              v-if="!readOnly"
              class="pi pi-bars text-surface-400 cursor-grab shrink-0 text-xs"
              aria-hidden="true"
            />
            <span class="sm:hidden text-xs text-surface-600 dark:text-surface-300 w-16 shrink-0">
              Canal
            </span>
            <InputNumber
              v-model="row.channel"
              :min="1"
              :max="999"
              :use-grouping="false"
              :disabled="readOnly"
              :input-class="[
                'w-full',
                isDuplicate(row) ? 'border-red-400 dark:border-red-500' : ''
              ]"
              class="w-full"
              :aria-label="`Canal, ligne ${index + 1}`"
              :input-props="{ inputmode: 'numeric' }"
            />
          </div>

          <div class="flex items-center gap-1 min-w-0">
            <span class="sm:hidden text-xs text-surface-600 dark:text-surface-300 w-16 shrink-0">
              Nom
            </span>
            <InputText
              v-model="row.name"
              :disabled="readOnly"
              :maxlength="FIELD_LIMITS.name"
              class="w-full"
              placeholder="KICK IN"
              :aria-label="`Nom, ligne ${index + 1}`"
            />
          </div>
          <div class="flex items-center gap-1 min-w-0">
            <span class="sm:hidden text-xs text-surface-600 dark:text-surface-300 w-16 shrink-0">
              Micro
            </span>
            <InputText
              v-model="row.microphone"
              :disabled="readOnly"
              :maxlength="FIELD_LIMITS.microphone"
              class="w-full"
              placeholder="Beta 91 ou équivalent"
              :aria-label="`Micro, ligne ${index + 1}`"
            />
          </div>
          <div class="flex items-center gap-1 min-w-0">
            <span class="sm:hidden text-xs text-surface-600 dark:text-surface-300 w-16 shrink-0">
              Routage
            </span>
            <InputText
              v-model="row.routing"
              :disabled="readOnly"
              :maxlength="FIELD_LIMITS.routing"
              class="w-full"
              placeholder="Vers split micro A1"
              :aria-label="`Routage, ligne ${index + 1}`"
            />
          </div>

          <div class="flex items-center gap-1 min-w-0">
            <span class="sm:hidden text-xs text-surface-600 dark:text-surface-300 w-16 shrink-0">
              Couleur
            </span>
            <Select
              v-model="row.colour"
              :options="COLOUR_OPTIONS"
              option-label="label"
              option-value="value"
              :disabled="readOnly"
              class="w-full"
              :aria-label="`Couleur, ligne ${index + 1}`"
            >
              <!-- The name travels with the swatch in both the closed state and the list, so
                   the grouping is never conveyed by colour alone. -->
              <template #value="{ value }">
                <span class="flex items-center gap-2">
                  <span
                    class="w-3 h-3 rounded-sm border border-surface-300 dark:border-surface-600 shrink-0"
                    :style="{ backgroundColor: hexFor(value) ?? 'transparent' }"
                    aria-hidden="true"
                  />
                  <span class="truncate">{{ labelFor(value) }}</span>
                </span>
              </template>
              <template #option="{ option }">
                <span class="flex items-center gap-2">
                  <span
                    class="w-3 h-3 rounded-sm border border-surface-300 dark:border-surface-600 shrink-0"
                    :style="{ backgroundColor: option.hex ?? 'transparent' }"
                    aria-hidden="true"
                  />
                  <span>{{ option.label }}</span>
                </span>
              </template>
            </Select>
          </div>

          <div v-if="!readOnly" class="flex items-center justify-end gap-0.5">
            <!-- Drag is not an accessible reorder, so the same move is available as buttons. -->
            <Button
              icon="pi pi-arrow-up"
              severity="secondary"
              text
              rounded
              size="small"
              :disabled="index === 0"
              :aria-label="`Monter la ligne ${index + 1}`"
              v-tooltip.top="'Monter'"
              @click="move(index, index - 1)"
            />
            <Button
              icon="pi pi-arrow-down"
              severity="secondary"
              text
              rounded
              size="small"
              :disabled="index === rows.length - 1"
              :aria-label="`Descendre la ligne ${index + 1}`"
              v-tooltip.top="'Descendre'"
              @click="move(index, index + 1)"
            />
            <Button
              icon="pi pi-trash"
              severity="danger"
              text
              rounded
              size="small"
              :aria-label="`Supprimer la ligne ${index + 1}${row.name ? ` (${row.name})` : ''}`"
              v-tooltip.top="'Supprimer'"
              @click="removeRow(index)"
            />
          </div>

          <p
            v-if="rowHasError(index)"
            class="text-xs text-red-700 dark:text-red-300 sm:col-span-6"
            role="alert"
          >
            {{ rowErrorText(index) }}
          </p>
        </li>
      </ul>
    </template>

    <Dialog
      v-model:visible="rangeDialogOpen"
      modal
      :header="`Colorer une plage (${label.toLowerCase()})`"
      :style="{ width: '30rem' }"
    >
      <form class="flex flex-col gap-4" @submit.prevent="applyRange">
        <p class="text-sm text-surface-600 dark:text-surface-300">
          Les couleurs marquent des groupes de lignes qui partent au même endroit.
        </p>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label :for="`${uid}-from`" class="block text-sm font-medium mb-1">Première ligne</label>
            <Select
              :id="`${uid}-from`"
              v-model="rangeFrom"
              :options="rowChoices"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
          <div>
            <label :for="`${uid}-to`" class="block text-sm font-medium mb-1">Dernière ligne</label>
            <Select
              :id="`${uid}-to`"
              v-model="rangeTo"
              :options="rowChoices"
              option-label="label"
              option-value="value"
              class="w-full"
            />
          </div>
        </div>
        <div>
          <label :for="`${uid}-colour`" class="block text-sm font-medium mb-1">Couleur</label>
          <Select
            :id="`${uid}-colour`"
            v-model="rangeColour"
            :options="COLOUR_OPTIONS"
            option-label="label"
            option-value="value"
            class="w-full"
          >
            <template #option="{ option }">
              <span class="flex items-center gap-2">
                <span
                  class="w-3 h-3 rounded-sm border border-surface-300 dark:border-surface-600 shrink-0"
                  :style="{ backgroundColor: option.hex ?? 'transparent' }"
                  aria-hidden="true"
                />
                <span>{{ option.label }}</span>
              </span>
            </template>
          </Select>
        </div>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" severity="secondary" text type="button" @click="rangeDialogOpen = false" />
          <Button label="Appliquer" type="submit" />
        </div>
      </form>
    </Dialog>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputNumber from 'primevue/inputnumber'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Select from 'primevue/select'
import { computed, ref, useId } from 'vue'
import { TECH_RIDER_COLOURS } from '../../../constants/techRiderColours.js'

const props = defineProps({
  label: { type: String, required: true },
  /**
   * Mutated in place. The parent owns the grid so it can diff both directions against one
   * snapshot and save them in a single request; handing every row edit back up as an event
   * would be the same state with a courier in front of it.
   */
  rows: { type: Array, required: true },
  maxRows: { type: Number, required: true },
  /** `{ list: string[], rows: { [index]: string[] } }`, as returned by the server for 422s. */
  errors: { type: Object, default: () => ({ list: [], rows: {} }) },
  readOnly: { type: Boolean, default: false }
})

const uid = useId()

// channel | name | micro | routage | couleur | actions
const GRID_COLUMNS = '6rem minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.3fr) 9rem auto'

/**
 * Mirrors the column lengths in App\Validator\BandSpace\TechRider\TechRiderPatchRows. Enforced
 * here as well as there so a paste that is too long stops at the field instead of coming back as
 * a rejected save of the whole grid. Pinned against the PHP constants by
 * tests/Unit/Validator/BandSpace/TechRider/TechRiderPatchLimitsTest.php.
 */
const FIELD_LIMITS = { name: 120, microphone: 120, routing: 180 }

const COLOUR_OPTIONS = [
  { value: null, label: 'Aucune', hex: null },
  ...TECH_RIDER_COLOURS.map((colour) => ({
    value: colour.value,
    label: colour.label,
    hex: colour.hex
  }))
]

let keyCounter = 0

const rangeDialogOpen = ref(false)
const rangeFrom = ref(0)
const rangeTo = ref(0)
const rangeColour = ref(null)

const draggedKey = ref(null)
const dropTargetKey = ref(null)

const isFull = computed(() => props.rows.length >= props.maxRows)
const listErrors = computed(() => props.errors.list ?? [])

/**
 * Flagged as you type rather than only on a rejected save. A duplicate channel is the mistake
 * this grid invites, and finding out at save time means finding out after the trip.
 */
const duplicateChannels = computed(() => {
  const seen = new Set()
  const duplicates = new Set()
  for (const row of props.rows) {
    if (row.channel === null || row.channel === undefined) continue
    if (seen.has(row.channel)) duplicates.add(row.channel)
    seen.add(row.channel)
  }
  return duplicates
})

/** Named by position and by what is in them, so picking a range needs no counting. */
const rowChoices = computed(() =>
  props.rows.map((row, index) => {
    const details = [row.channel ? `canal ${row.channel}` : null, row.name || null].filter(Boolean)
    return {
      value: index,
      label:
        details.length > 0 ? `Ligne ${index + 1} (${details.join(', ')})` : `Ligne ${index + 1}`
    }
  })
)

function isDuplicate(row) {
  return duplicateChannels.value.has(row.channel)
}

function hexFor(value) {
  return COLOUR_OPTIONS.find((option) => option.value === value)?.hex ?? null
}

function labelFor(value) {
  return COLOUR_OPTIONS.find((option) => option.value === value)?.label ?? 'Aucune'
}

function rowHasError(index) {
  return (props.errors.rows?.[index]?.length ?? 0) > 0
}

function rowErrorText(index) {
  return (props.errors.rows?.[index] ?? []).join('. ')
}

/** One past the highest in use, so adding rows in order never lands on a duplicate. */
function nextChannel() {
  const used = props.rows.map((row) => row.channel ?? 0)
  return used.length === 0 ? 1 : Math.max(...used) + 1
}

function addRow() {
  if (isFull.value) return
  props.rows.push({
    // A client-side key, not the server id: a full replace regenerates every id, and a row that
    // has never been saved has none at all, so v-for cannot be keyed on it.
    key: `row-${uid}-${keyCounter++}`,
    channel: nextChannel(),
    name: '',
    microphone: '',
    routing: '',
    colour: null
  })
}

function removeRow(index) {
  props.rows.splice(index, 1)
}

function move(fromIndex, toIndex) {
  if (toIndex < 0 || toIndex >= props.rows.length) return
  const [moved] = props.rows.splice(fromIndex, 1)
  props.rows.splice(toIndex, 0, moved)
}

/** Rewrites channels to 1..N in the order shown. Local only, written on the next save. */
function renumber() {
  props.rows.forEach((row, index) => {
    row.channel = index + 1
  })
}

function openRangeDialog() {
  rangeFrom.value = 0
  rangeTo.value = props.rows.length - 1
  rangeColour.value = null
  rangeDialogOpen.value = true
}

/**
 * Sorted, so picking the last row first still colours the range the user drew rather than
 * silently doing nothing.
 */
function applyRange() {
  const [start, end] = [rangeFrom.value, rangeTo.value].sort((a, b) => a - b)
  for (let index = start; index <= end; index++) {
    if (props.rows[index]) props.rows[index].colour = rangeColour.value
  }
  rangeDialogOpen.value = false
}

function handleDragStart(key) {
  if (props.readOnly) return
  draggedKey.value = key
}

function handleDragOver(key) {
  if (!draggedKey.value || draggedKey.value === key) return
  dropTargetKey.value = key
}

function handleDragLeave(key) {
  if (dropTargetKey.value === key) dropTargetKey.value = null
}

// dragend always fires on the source, so a drop outside the list cannot leave a row stuck.
function handleDragEnd() {
  draggedKey.value = null
  dropTargetKey.value = null
}

function handleDrop(targetKey) {
  const sourceKey = draggedKey.value
  dropTargetKey.value = null
  draggedKey.value = null
  if (!sourceKey || sourceKey === targetKey) return

  const fromIndex = props.rows.findIndex((row) => row.key === sourceKey)
  const toIndex = props.rows.findIndex((row) => row.key === targetKey)
  if (fromIndex === -1 || toIndex === -1) return

  move(fromIndex, toIndex)
}
</script>
