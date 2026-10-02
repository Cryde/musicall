<template>
  <nav aria-label="Sections du rider" class="flex flex-col gap-3">
    <div class="flex items-baseline justify-between gap-2 px-1">
      <h2 class="text-sm font-bold uppercase tracking-wide text-surface-600 dark:text-surface-300">Sections</h2>
      <span v-if="!readOnly && localItems.length > 1" class="text-xs text-surface-600 dark:text-surface-300">
        glisser ou ⋮ pour réordonner
      </span>
    </div>

    <VueDraggable
      v-model="localItems"
      tag="ul"
      handle=".rider-outline-handle"
      :animation="150"
      :disabled="readOnly"
      ghost-class="opacity-30"
      class="m-0 p-0 list-none flex flex-col gap-1"
      @end="handleDragEnd"
    >
      <li
        v-for="item in localItems"
        :key="item.id"
        :class="[
          'group flex items-center gap-1 rounded-lg pr-1',
          item.id === selectedId ? 'bg-primary-100 dark:bg-surface-800' : 'hover:bg-surface-100 dark:hover:bg-surface-800'
        ]"
      >
        <span
          v-if="!readOnly"
          class="rider-outline-handle cursor-grab px-1 text-surface-500 dark:text-surface-400"
          aria-hidden="true"
        >
          <i class="pi pi-bars text-xs" />
        </span>

        <button
          type="button"
          :aria-current="item.id === selectedId ? 'true' : undefined"
          :class="[
            'flex items-center gap-2 grow min-w-0 h-10 px-2 rounded-lg text-left cursor-pointer',
            // Italic rather than faded: the eye and the screen reader text already say it is hidden,
            // and a faded title fell below a readable contrast.
            item.is_included ? '' : 'italic text-surface-600 dark:text-surface-300',
            item.id === selectedId ? 'font-semibold text-primary-800 dark:text-surface-0' : ''
          ]"
          @click="emit('select', item.id)"
        >
          <i :class="['pi', riderSectionTypeIcon(item.type), 'shrink-0 text-surface-600 dark:text-surface-300']" aria-hidden="true" />
          <span class="truncate">{{ item.title }}</span>
          <span v-if="!item.is_included" class="sr-only">, masquée dans le PDF</span>
          <span class="flex-1" />
          <!-- Shape as well as colour: hollow for empty, filled for written. -->
          <span
            :class="[
              'shrink-0 size-2.5 rounded-full',
              item.is_empty ? 'border-2 border-surface-500 dark:border-surface-400' : 'bg-teal-600 dark:bg-teal-400'
            ]"
            :title="item.is_empty ? 'Vide' : 'Rempli'"
            aria-hidden="true"
          />
          <span class="sr-only">{{ item.is_empty ? ', vide' : ', remplie' }}</span>
        </button>

        <template v-if="!readOnly">
          <button
            type="button"
            :aria-pressed="item.is_included"
            :aria-label="`Visible dans le PDF : ${item.title}`"
            class="shrink-0 size-8 inline-flex items-center justify-center rounded-lg cursor-pointer text-surface-600 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-700"
            @click="emit('toggle-included', item)"
          >
            <i :class="['pi', item.is_included ? 'pi-eye' : 'pi-eye-slash']" aria-hidden="true" />
          </button>
          <button
            type="button"
            aria-haspopup="menu"
            :aria-label="`Plus d'actions : ${item.title}`"
            class="shrink-0 size-8 inline-flex items-center justify-center rounded-lg cursor-pointer text-surface-600 dark:text-surface-300 hover:bg-surface-200 dark:hover:bg-surface-700"
            @click="(event) => openRowMenu(event, item)"
          >
            <i class="pi pi-ellipsis-v" aria-hidden="true" />
          </button>
        </template>
      </li>
    </VueDraggable>

    <p class="sr-only" aria-live="polite">{{ announcement }}</p>

    <!-- One menu for every row: drag is not an accessible reorder, so the same move is offered here. -->
    <Menu ref="rowMenuRef" :model="rowMenuItems" :popup="true" />

    <RiderAddSectionButton
      v-if="!readOnly"
      :present-types="localItems.map((item) => item.type)"
      :adding="adding"
      @add="(type) => emit('add', type)"
    />
  </nav>
</template>

<script setup>
import Menu from 'primevue/menu'
import { computed, ref, watch } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import { riderSectionTypeIcon } from '../../../constants/techRider.js'
import { movedIds } from '../../../utils/riderWorkspace.js'
import RiderAddSectionButton from './RiderAddSectionButton.vue'

/**
 * The rider's sections as an outline (#1091): select one to edit it, show or hide it in the PDF,
 * reorder by dragging or from the row menu, and add a section by type.
 */
const props = defineProps({
  items: { type: Array, required: true },
  selectedId: { type: String, default: null },
  readOnly: { type: Boolean, default: false },
  adding: { type: Boolean, default: false }
})

const emit = defineEmits(['select', 'reorder', 'toggle-included', 'add'])

// The draggable list owns its own copy; the store's order comes back through `items` once saved.
const localItems = ref([...props.items])
watch(
  () => props.items,
  (items) => {
    localItems.value = [...items]
  }
)

const rowMenuRef = ref(null)
const rowMenuTarget = ref(null)
const announcement = ref('')

const rowMenuItems = computed(() => {
  const ids = localItems.value.map((item) => item.id)
  const id = rowMenuTarget.value?.id
  return [
    {
      label: 'Monter',
      icon: 'pi pi-arrow-up',
      disabled: !movedIds(ids, id, -1),
      command: () => move(-1)
    },
    {
      label: 'Descendre',
      icon: 'pi pi-arrow-down',
      disabled: !movedIds(ids, id, 1),
      command: () => move(1)
    }
  ]
})

function openRowMenu(event, item) {
  rowMenuTarget.value = item
  rowMenuRef.value.toggle(event)
}

function move(step) {
  const ids = movedIds(
    localItems.value.map((item) => item.id),
    rowMenuTarget.value?.id,
    step
  )
  if (!ids) return
  emit('reorder', ids)
  announcement.value = `« ${rowMenuTarget.value.title} » déplacée en position ${ids.indexOf(rowMenuTarget.value.id) + 1}`
}

function handleDragEnd() {
  const ids = localItems.value.map((item) => item.id)
  if (ids.join() !== props.items.map((item) => item.id).join()) emit('reorder', ids)
}
</script>
