<template>
  <section class="flex flex-col gap-2 min-w-0" :aria-label="label">
    <!-- No heading: the tab above names the direction. -->
    <div class="flex flex-wrap items-center gap-2 min-h-9">
      <template v-if="selectedRows.length > 0">
        <div
          role="toolbar"
          :aria-label="`Actions sur la sélection (${label.toLowerCase()})`"
          class="flex flex-wrap items-center gap-1 rounded-lg px-2 py-1 bg-primary-50 dark:bg-primary-950/40 text-sm"
        >
          <span class="font-medium px-1" role="status">{{ selectionLabel }}</span>
          <Button
            label="Couleur"
            icon="pi pi-palette"
            severity="secondary"
            text
            size="small"
            aria-haspopup="menu"
            @click="(event) => selectionColourMenuRef.toggle(event)"
          />
          <Button
            label="Renuméroter"
            icon="pi pi-sort-numeric-down"
            severity="secondary"
            text
            size="small"
            @click="renumberSelection"
          />
          <Button label="Supprimer" icon="pi pi-trash" severity="danger" text size="small" @click="removeSelection" />
          <Button
            icon="pi pi-times"
            severity="secondary"
            text
            rounded
            size="small"
            aria-label="Désélectionner"
            @click="clearSelection"
          />
        </div>
      </template>
      <template v-else>
        <span
          class="text-xs px-2 py-0.5 rounded bg-surface-100 dark:bg-surface-800 text-surface-700 dark:text-surface-200"
          :class="isFull ? 'text-red-700 dark:text-red-300' : ''"
        >
          {{ rows.length }} / {{ maxRows }}
        </span>
        <span class="flex-1" />
        <Button
          v-if="!readOnly"
          label="Renuméroter"
          icon="pi pi-sort-numeric-down"
          severity="secondary"
          text
          size="small"
          :disabled="rows.length === 0"
          v-tooltip.top="'Renumérote les canaux à la suite dans l\'ordre affiché'"
          :aria-label="`Renuméroter les canaux (${label.toLowerCase()})`"
          @click="renumberAll"
        />
      </template>
    </div>

    <Message v-if="isFull && !readOnly" severity="warn" :closable="false" size="small">
      La limite de {{ maxRows }} lignes est atteinte.
    </Message>

    <Message v-for="(message, index) in listErrors" :key="index" severity="error" :closable="false" size="small">
      {{ message }}
    </Message>

    <p v-if="readOnly && rows.length === 0" class="text-sm text-surface-600 dark:text-surface-300 py-3">
      Aucune ligne.
    </p>

    <!-- A table you type into, as in a spreadsheet: no form per row. It scrolls sideways inside its
         own box on a phone rather than squeezing every cell to nothing. Relative, so the sr-only
         labels inside, which are absolutely positioned, are clipped with it and do not widen the page. -->
    <div v-else ref="tableBoxRef" class="relative overflow-x-auto" @focusout="handleTableFocusOut">
      <table class="w-full min-w-[40rem] table-fixed border-collapse text-sm">
        <caption class="sr-only">{{ label }}</caption>
        <colgroup>
          <col class="w-16" />
          <col class="w-24" />
          <col />
          <col />
          <col />
          <col class="w-11" />
        </colgroup>
        <thead>
          <tr class="text-xs font-medium text-left text-surface-600 dark:text-surface-300">
            <th scope="col"><span class="sr-only">Sélection</span></th>
            <th scope="col" class="px-2 py-1.5">{{ LABELS.channel }}</th>
            <th scope="col" class="px-2 py-1.5">{{ LABELS.name }}</th>
            <th scope="col" class="px-2 py-1.5">{{ LABELS.microphone }}</th>
            <th scope="col" class="px-2 py-1.5">{{ LABELS.routing }}</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <template v-for="(row, index) in rows" :key="row.key">
            <tr
              class="group border-t border-surface-200 dark:border-surface-700"
              :class="[
                selectedKeys.has(row.key) ? 'bg-primary-50 dark:bg-primary-950/30' : '',
                rowHasError(index) ? 'bg-red-50 dark:bg-red-950/30' : '',
                dropTargetKey === row.key ? 'outline-2 outline-primary-400 -outline-offset-2' : ''
              ]"
              @dragover="(event) => handleDragOver(event, row.key)"
              @dragleave="handleDragLeave(row.key)"
              @drop="(event) => handleDrop(event, row.key)"
            >
              <td class="px-1">
                <div v-if="!readOnly" class="flex items-center gap-1" :class="ROW_TOOL_VISIBILITY">
                  <!-- Only the handle drags: a draggable row would steal the mouse from its inputs. -->
                  <span
                    class="p-1 cursor-grab text-surface-500 dark:text-surface-400"
                    draggable="true"
                    aria-hidden="true"
                    @dragstart="(event) => handleDragStart(event, row.key)"
                    @dragend="handleDragEnd"
                  >
                    <i class="pi pi-bars text-xs" />
                  </span>
                  <input
                    type="checkbox"
                    class="w-4 h-4 accent-primary-600"
                    :checked="selectedKeys.has(row.key)"
                    :aria-label="`Sélectionner la ligne ${index + 1}${row.name ? ` (${row.name})` : ''}`"
                    @click="(event) => handleSelectClick(event, row.key)"
                  />
                </div>
              </td>
              <td :data-cell="`${index}:channel`" v-bind="cellHandlers(index, 'channel')">
                <div class="flex items-center gap-1">
                  <span
                    v-if="row.colour"
                    class="w-2.5 h-2.5 rounded-full shrink-0 ml-1"
                    :style="{ backgroundColor: hexFor(row.colour) }"
                    :title="labelFor(row.colour)"
                  >
                    <span class="sr-only">Couleur : {{ labelFor(row.colour) }}</span>
                  </span>
                  <input
                    :value="row.channel ?? ''"
                    inputmode="numeric"
                    :readonly="readOnly"
                    :class="[CELL_INPUT, 'tabular-nums', clashes(row) ? CELL_INPUT_CLASH : '']"
                    :aria-label="`${LABELS.channel}, ligne ${index + 1}${row.stereo ? ', stéréo' : ''}`"
                    @input="(event) => (row.channel = parseChannel(event.target.value))"
                  />
                  <span v-if="row.stereo" class="shrink-0 pr-1 tabular-nums text-surface-600 dark:text-surface-300">
                    -{{ row.channel === null ? '' : row.channel + 1 }}
                  </span>
                </div>
              </td>
              <td :data-cell="`${index}:name`" v-bind="cellHandlers(index, 'name')">
                <input
                  v-model="row.name"
                  :readonly="readOnly"
                  :maxlength="FIELD_LIMITS.name"
                  :class="CELL_INPUT"
                  :aria-label="`${LABELS.name}, ligne ${index + 1}`"
                />
              </td>
              <td :data-cell="`${index}:microphone`" v-bind="cellHandlers(index, 'microphone')">
                <AutoComplete
                  :model-value="row.microphone"
                  :suggestions="microphoneGroups"
                  option-label="name"
                  option-group-label="label"
                  option-group-children="items"
                  complete-on-focus
                  :delay="0"
                  empty-search-message="Aucune suggestion, saisissez librement"
                  search-message="{0} suggestions"
                  selection-message="{0} sélectionné"
                  :disabled="readOnly"
                  :input-class="CELL_AUTOCOMPLETE"
                  class="w-full"
                  :aria-label="`${LABELS.microphone}, ligne ${index + 1}`"
                  @complete="(event) => (microphoneQuery = event.query)"
                  @update:model-value="(value) => (row.microphone = microphoneValue(value))"
                >
                  <template #optiongroup="{ option }">
                    <span class="text-xs font-semibold text-surface-600 dark:text-surface-300">{{ option.label }}</span>
                  </template>
                  <template #option="{ option }">
                    <span class="flex items-center justify-between gap-3 w-full">
                      <span>{{ option.name }}</span>
                      <span v-if="option.usageCount" class="text-xs text-surface-600 dark:text-surface-300">
                        ×{{ option.usageCount }}
                      </span>
                    </span>
                  </template>
                </AutoComplete>
              </td>
              <td :data-cell="`${index}:routing`" v-bind="cellHandlers(index, 'routing')">
                <input
                  v-model="row.routing"
                  :readonly="readOnly"
                  :maxlength="FIELD_LIMITS.routing"
                  :class="CELL_INPUT"
                  :aria-label="`${LABELS.routing}, ligne ${index + 1}`"
                />
              </td>
              <td class="text-right">
                <Button
                  v-if="!readOnly"
                  icon="pi pi-ellipsis-v"
                  severity="secondary"
                  text
                  rounded
                  size="small"
                  :class="ROW_TOOL_VISIBILITY"
                  aria-haspopup="menu"
                  :aria-label="`Actions de la ligne ${index + 1}${row.name ? ` (${row.name})` : ''}`"
                  @click="(event) => openRowMenu(event, index)"
                />
              </td>
            </tr>
            <tr v-if="rowHasError(index)" class="bg-red-50 dark:bg-red-950/30">
              <td />
              <td colspan="5" class="px-2 pb-2 text-xs text-red-700 dark:text-red-300" role="alert">
                {{ rowErrorText(index) }}
              </td>
            </tr>
          </template>

          <!-- Always there to type into: typing makes it the next entry, numbered, and a new empty
               row takes its place. The examples only ever show here, in italics, so they can never
               be read as values. -->
          <tr v-if="!readOnly && !isFull" class="border-t border-surface-200 dark:border-surface-700">
            <td />
            <td :data-cell="'blank:channel'" v-bind="cellHandlers('blank', 'channel')">
              <input
                inputmode="numeric"
                :placeholder="String(nextChannel(rows) ?? '')"
                :class="[CELL_INPUT, PLACEHOLDER_STYLE, 'tabular-nums']"
                :aria-label="`${LABELS.channel}, nouvelle entrée`"
                @input="(event) => promote(event, 'channel', parseChannel(event.target.value))"
                @compositionend="(event) => promote(event, 'channel', parseChannel(event.target.value))"
              />
            </td>
            <td v-for="field in TEXT_FIELDS" :key="field" :data-cell="`blank:${field}`" v-bind="cellHandlers('blank', field)">
              <input
                :maxlength="FIELD_LIMITS[field]"
                :placeholder="field === 'name' ? 'Ajouter une entrée…' : PLACEHOLDERS[field]"
                :class="[CELL_INPUT, PLACEHOLDER_STYLE]"
                :aria-label="`${LABELS[field]}, nouvelle entrée`"
                @input="(event) => promote(event, field, event.target.value)"
                @compositionend="(event) => promote(event, field, event.target.value)"
              />
            </td>
            <td />
          </tr>
        </tbody>
      </table>
    </div>

    <TieredMenu ref="rowMenuRef" :model="rowMenuItems" popup>
      <template #item="{ item, props: itemProps, hasSubmenu }">
        <a v-bind="itemProps.action" :class="['flex items-center gap-2', item.danger ? 'text-red-700 dark:text-red-300' : '']">
          <span
            v-if="item.swatch !== undefined"
            class="w-3 h-3 rounded-sm border border-surface-300 dark:border-surface-600 shrink-0"
            :style="{ backgroundColor: item.swatch ?? 'transparent' }"
            aria-hidden="true"
          />
          <i v-else-if="item.icon" :class="item.icon" aria-hidden="true" />
          <span class="flex-1">{{ item.label }}</span>
          <i v-if="item.checked" class="pi pi-check text-xs" aria-hidden="true" />
          <i v-if="hasSubmenu" class="pi pi-angle-right text-xs" aria-hidden="true" />
        </a>
      </template>
    </TieredMenu>

    <Menu ref="selectionColourMenuRef" :model="selectionColourItems" popup>
      <template #item="{ item, props: itemProps }">
        <a v-bind="itemProps.action" class="flex items-center gap-2">
          <span
            class="w-3 h-3 rounded-sm border border-surface-300 dark:border-surface-600 shrink-0"
            :style="{ backgroundColor: item.swatch ?? 'transparent' }"
            aria-hidden="true"
          />
          <span>{{ item.label }}</span>
        </a>
      </template>
    </Menu>
  </section>
</template>

<script setup>
import AutoComplete from 'primevue/autocomplete'
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Message from 'primevue/message'
import TieredMenu from 'primevue/tieredmenu'
import { computed, nextTick, ref, useId, watch } from 'vue'
import { TECH_RIDER_COLOURS } from '../../../constants/techRiderColours.js'
import {
  PATCH_LIST_LABELS as LABELS,
  PATCH_LIST_COLUMNS
} from '../../../constants/techRiderPatchColumns.js'
import {
  duplicateChannels,
  microphoneSuggestionGroups,
  nextChannel,
  parseChannel,
  renumberedChannels,
  rowClashes,
  selectionAfterClick
} from '../../../utils/patchGrid.js'

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
  readOnly: { type: Boolean, default: false },
  /** `{ used: [{ name, usage_count }], catalogue: [name] }`, for the Micro cells. */
  microphoneSuggestions: { type: Object, default: () => ({ used: [], catalogue: [] }) }
})

/** Leaving a cell means the edit in it is done, so the parent saves without waiting. */
const emit = defineEmits(['cell-left'])

const uid = useId()

const TEXT_FIELDS = ['name', 'microphone', 'routing']

/**
 * Mirrors the column lengths in App\Validator\BandSpace\TechRider\TechRiderPatchRows. Enforced
 * here as well as there so a paste that is too long stops at the field instead of coming back as
 * a rejected save of the whole grid. Pinned against the PHP constants by
 * tests/Unit/Validator/BandSpace/TechRider/TechRiderPatchLimitsTest.php.
 */
const FIELD_LIMITS = { name: 120, microphone: 120, routing: 180 }

// A cell reads as text until hovered or focused, the way a spreadsheet does.
const CELL_INPUT =
  'w-full min-w-0 bg-transparent rounded px-2 py-1.5 border border-transparent hover:border-surface-300 dark:hover:border-surface-600 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/30'
// PrimeVue paints its own input box and focus shadow, which would make this one cell look like a form.
const CELL_AUTOCOMPLETE =
  '!w-full !bg-transparent !shadow-none !rounded !px-2 !py-1.5 !border !border-transparent hover:!border-surface-300 dark:hover:!border-surface-600 focus:!border-primary-500 focus:!ring-2 focus:!ring-primary-500/30'
const CELL_INPUT_CLASH = '!border-red-500 text-red-700 dark:text-red-300'

// Italic and lighter than a value, so an example is never read as one, yet still at a readable
// contrast: surface-500 on white, surface-400 on the dark background.
const PLACEHOLDER_STYLE =
  'placeholder:italic placeholder:text-surface-500 dark:placeholder:text-surface-400'

// Shown on hover or focus within the row, and always where there is no hover to reveal them.
const ROW_TOOL_VISIBILITY =
  'opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 [@media(hover:none)]:opacity-100'

const PLACEHOLDERS = Object.fromEntries(
  PATCH_LIST_COLUMNS.map(({ field, placeholder }) => [field, placeholder])
)

const COLOUR_OPTIONS = [
  { value: null, label: 'Aucune', hex: null },
  ...TECH_RIDER_COLOURS.map((colour) => ({
    value: colour.value,
    label: colour.label,
    hex: colour.hex
  }))
]

let keyCounter = 0

const tableBoxRef = ref(null)
const rowMenuRef = ref(null)
const selectionColourMenuRef = ref(null)
const menuRowKey = ref(null)
const microphoneQuery = ref('')
const draggedKey = ref(null)
const dropTargetKey = ref(null)
const selectedKeys = ref(new Set())
const selectionAnchor = ref(null)
// What a cell held when it took focus, for Échap.
let focusedCell = null

const isFull = computed(() => props.rows.length >= props.maxRows)
const listErrors = computed(() => props.errors.list ?? [])

/**
 * Flagged as you type rather than only on a rejected save. A duplicate channel is the mistake
 * this grid invites, and finding out at save time means finding out after the trip.
 */
const duplicates = computed(() => duplicateChannels(props.rows))

const microphoneGroups = computed(() =>
  microphoneSuggestionGroups(microphoneQuery.value ?? '', props.microphoneSuggestions)
)

const selectedRows = computed(() => props.rows.filter((row) => selectedKeys.value.has(row.key)))
const selectionLabel = computed(() =>
  selectedRows.value.length > 1 ? `${selectedRows.value.length} lignes` : '1 ligne'
)

// A deleted row cannot stay selected.
watch(
  () => props.rows.map((row) => row.key).join(),
  () => {
    const keys = new Set(props.rows.map((row) => row.key))
    selectedKeys.value = new Set([...selectedKeys.value].filter((key) => keys.has(key)))
  }
)

function clashes(row) {
  return rowClashes(row, duplicates.value)
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

/** A picked suggestion arrives as its option, a typed one as text. */
function microphoneValue(value) {
  return typeof value === 'string' ? value : (value?.name ?? '')
}

function newRow(values = {}) {
  return {
    // A client-side key, not the server id: a full replace regenerates every id, and a row that
    // has never been saved has none at all, so v-for cannot be keyed on it.
    key: `row-${uid}-${keyCounter++}`,
    channel: nextChannel(props.rows),
    stereo: false,
    name: '',
    microphone: '',
    routing: '',
    colour: null,
    ...values
  }
}

// Keyboard and focus, per cell -------------------------------------------------------------

function cellHandlers(index, field) {
  return {
    onFocusin: () => rememberCell(index, field),
    onKeydown: (event) => handleCellKeydown(event, index, field),
    // Caught on the way down: the suggestion list cancels every Échap before it bubbles here.
    onKeydownCapture: (event) => handleCellEscape(event, index, field)
  }
}

/**
 * Saves as soon as focus leaves the table, which says the editing is over. Moving between cells
 * leaves it to the autosave's debounce: a save replaces the whole list, one per cell is churn. A
 * suggestion list lives outside the table, so clicking an option is not leaving it.
 */
function handleTableFocusOut(event) {
  const next = event.relatedTarget
  if (next && (tableBoxRef.value?.contains(next) || next.closest?.('.p-autocomplete-overlay')))
    return
  emit('cell-left')
}

/** An Entrée that confirms an input method's composition is not one of ours. */
function isComposing(event) {
  return event.isComposing || event.keyCode === 229
}

function rememberCell(index, field) {
  const row = index === 'blank' ? null : props.rows[index]
  focusedCell = row ? { key: row.key, field, value: row[field] } : null
}

async function focusCell(index, field) {
  await nextTick()
  const input = tableBoxRef.value?.querySelector(`[data-cell="${index}:${field}"] input`)
  if (!input) return
  input.focus()
  input.setSelectionRange?.(input.value.length, input.value.length)
}

/**
 * Entrée goes down a row in the same column, onto the empty row after the last. Échap puts back
 * what the cell held when it was entered. Both step aside when the suggestion list used the key.
 */
function handleCellKeydown(event, index, field) {
  if (event.defaultPrevented || props.readOnly || event.key !== 'Enter' || isComposing(event))
    return

  event.preventDefault()
  const below = index === 'blank' ? 'blank' : index + 1 < props.rows.length ? index + 1 : 'blank'
  focusCell(below, field)
}

function handleCellEscape(event, index, field) {
  if (event.key !== 'Escape' || props.readOnly || !focusedCell || index === 'blank') return
  const row = props.rows[index]
  if (row?.key !== focusedCell.key) return
  row[field] = focusedCell.value
}

/**
 * The first keystroke in the empty row makes it a real entry, and the cursor moves with it. The
 * empty row is never bound to anything, so its input is cleared by hand for the next one.
 */
function promote(event, field, value) {
  // Mid-composition the text is not final, and clearing it would break the word being typed.
  if (event.isComposing) return
  event.target.value = ''
  if (isFull.value || value === null || value === '') return
  props.rows.push(newRow({ [field]: value }))
  focusCell(props.rows.length - 1, field)
}

// Row actions -------------------------------------------------------------------------------

function openRowMenu(event, index) {
  menuRowKey.value = props.rows[index]?.key ?? null
  rowMenuRef.value.toggle(event)
}

// By key, the index found again on click: rows can move while the menu is open.
const rowMenuItems = computed(() => {
  const key = menuRowKey.value
  const row = props.rows.find((candidate) => candidate.key === key)
  if (!row) return []
  const index = props.rows.indexOf(row)
  const at = () => props.rows.findIndex((candidate) => candidate.key === key)

  return [
    {
      label: 'Insérer au-dessus',
      icon: 'pi pi-arrow-up',
      disabled: isFull.value,
      command: () => insertAt(at())
    },
    {
      label: 'Insérer en dessous',
      icon: 'pi pi-arrow-down',
      disabled: isFull.value,
      command: () => insertAt(at() + 1)
    },
    {
      label: 'Dupliquer',
      icon: 'pi pi-copy',
      disabled: isFull.value,
      command: () => duplicateRow(at())
    },
    {
      // Says what it will do, as the check mark alone is not read out.
      label: row.stereo ? 'Repasser en mono (1 canal)' : 'Canal stéréo (2 canaux)',
      icon: 'pi pi-arrows-h',
      command: () => {
        row.stereo = !row.stereo
      }
    },
    {
      label: 'Couleur',
      icon: 'pi pi-palette',
      items: COLOUR_OPTIONS.map((option) => ({
        label: option.label,
        swatch: option.hex,
        checked: row.colour === option.value,
        command: () => {
          row.colour = option.value
        }
      }))
    },
    { separator: true },
    // Drag is not an accessible reorder, so the same move is here too.
    {
      label: 'Monter',
      icon: 'pi pi-chevron-up',
      disabled: index === 0,
      command: () => move(at(), at() - 1)
    },
    {
      label: 'Descendre',
      icon: 'pi pi-chevron-down',
      disabled: index === props.rows.length - 1,
      command: () => move(at(), at() + 1)
    },
    { separator: true },
    {
      label: 'Supprimer l’entrée',
      icon: 'pi pi-trash',
      danger: true,
      command: () => props.rows.splice(at(), 1)
    }
  ]
})

function insertAt(index) {
  props.rows.splice(index, 0, newRow())
  focusCell(index, 'name')
}

function duplicateRow(index) {
  const { key: _key, channel: _channel, ...values } = props.rows[index]
  props.rows.splice(index + 1, 0, newRow(values))
}

function move(fromIndex, toIndex) {
  if (toIndex < 0 || toIndex >= props.rows.length) return
  const [moved] = props.rows.splice(fromIndex, 1)
  props.rows.splice(toIndex, 0, moved)
}

/** Rewrites channels to follow on in the order shown. Local only, written on the next save. */
function renumberAll() {
  const channels = renumberedChannels(props.rows)
  props.rows.forEach((row, index) => {
    row.channel = channels[index]
  })
}

// Selection ---------------------------------------------------------------------------------

function handleSelectClick(event, key) {
  const { selected, anchorKey } = selectionAfterClick(
    props.rows.map((row) => row.key),
    selectedKeys.value,
    selectionAnchor.value,
    key,
    event.shiftKey
  )
  selectedKeys.value = selected
  selectionAnchor.value = anchorKey
}

function clearSelection() {
  selectedKeys.value = new Set()
  selectionAnchor.value = null
}

const selectionColourItems = computed(() =>
  COLOUR_OPTIONS.map((option) => ({
    label: option.label,
    swatch: option.hex,
    command: () => {
      for (const row of selectedRows.value) row.colour = option.value
    }
  }))
)

/** The selected rows follow on from the first of them, the others keep their channels. */
function renumberSelection() {
  const rows = selectedRows.value
  const channels = renumberedChannels(rows, rows[0]?.channel ?? 1)
  rows.forEach((row, index) => {
    row.channel = channels[index]
  })
}

function removeSelection() {
  for (let index = props.rows.length - 1; index >= 0; index--) {
    if (selectedKeys.value.has(props.rows[index].key)) props.rows.splice(index, 1)
  }
  clearSelection()
}

// Drag and drop -----------------------------------------------------------------------------

function handleDragStart(event, key) {
  if (props.readOnly) return
  draggedKey.value = key
  // Firefox starts no drag without data, and the whole row is what is being moved.
  event.dataTransfer.setData('text/plain', key)
  event.dataTransfer.effectAllowed = 'move'
  const row = event.target.closest('tr')
  if (row) event.dataTransfer.setDragImage(row, 16, 16)
}

// Only a row being dragged is taken over: text dropped into a cell from elsewhere still lands.
function handleDragOver(event, key) {
  if (!draggedKey.value) return
  event.preventDefault()
  if (draggedKey.value !== key) dropTargetKey.value = key
}

function handleDragLeave(key) {
  if (dropTargetKey.value === key) dropTargetKey.value = null
}

// dragend always fires on the source, so a drop outside the list cannot leave a row stuck.
function handleDragEnd() {
  draggedKey.value = null
  dropTargetKey.value = null
}

function handleDrop(event, targetKey) {
  const sourceKey = draggedKey.value
  if (!sourceKey) return
  event.preventDefault()
  dropTargetKey.value = null
  draggedKey.value = null
  if (!sourceKey || sourceKey === targetKey) return

  const fromIndex = props.rows.findIndex((row) => row.key === sourceKey)
  const toIndex = props.rows.findIndex((row) => row.key === targetKey)
  if (fromIndex === -1 || toIndex === -1) return

  move(fromIndex, toIndex)
}
</script>
