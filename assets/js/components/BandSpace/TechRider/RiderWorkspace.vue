<template>
  <div ref="rootRef" class="grid md:grid-cols-[18rem_minmax(0,1fr)] gap-6">
    <!-- Below md the outline would squeeze the editor, so it becomes a picker above it. -->
    <div class="md:hidden flex flex-col gap-2">
      <label for="rider-section-picker" class="text-sm font-medium">Section</label>
      <Select
        input-id="rider-section-picker"
        :model-value="selectedId"
        :options="pickerOptions"
        option-label="label"
        option-value="id"
        class="w-full"
        @update:model-value="selectItem"
      />
      <RiderAddSectionButton
        v-if="!readOnly"
        :present-types="items.map((item) => item.type)"
        :adding="isAdding"
        @add="handleAdd"
      />
    </div>

    <!-- The column carries the separator at full height, the outline sticks inside it. -->
    <div class="hidden md:block md:border-r md:pr-6 border-surface-200 dark:border-surface-700">
      <!-- Padded inside the scroll box, which clips both axes, so focus rings are not cut at the edges. -->
      <div class="md:sticky md:top-4 md:max-h-[calc(100vh-2rem)] md:overflow-y-auto -mx-1 px-1 py-1">
        <RiderOutline
          :items="items"
          :selected-id="selectedId"
          :read-only="readOnly"
          :adding="isAdding"
          @select="selectItem"
          @reorder="handleReorder"
          @toggle-included="handleToggleIncluded"
          @add="handleAdd"
        />
      </div>
    </div>

    <RiderSectionPanel
      v-if="selectedItem"
      ref="panelRef"
      :band-space-id="bandSpaceId"
      :rider-id="riderId"
      :item="selectedItem"
      :read-only="readOnly"
      :can-move-up="movedIds(ids, selectedItem.id, -1) !== null"
      :can-move-down="movedIds(ids, selectedItem.id, 1) !== null"
      @move="(step) => handleMove(selectedItem.id, step)"
      @delete="confirmDelete(selectedItem)"
      @toggle-included="handleToggleIncluded"
    />
    <p v-else class="text-surface-600 dark:text-surface-300 py-8 text-center">
      Ce tech rider n'a aucune section.{{ readOnly ? '' : ' Ajoutez-en une pour commencer.' }}
    </p>
  </div>
</template>

<script setup>
import Select from 'primevue/select'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'
import { movedIds, neighbourAfterRemoval, selectableItemId } from '../../../utils/riderWorkspace.js'
import RiderAddSectionButton from './RiderAddSectionButton.vue'
import RiderOutline from './RiderOutline.vue'
import RiderSectionPanel from './RiderSectionPanel.vue'

/**
 * The tech rider editor (#1091): the outline of its sections, and the one being edited. The section
 * on screen lives in the query (`?item=`), so a reload or a shared link reopens it.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  riderId: { type: String, required: true },
  items: { type: Array, required: true },
  readOnly: { type: Boolean, default: false }
})

const techRidersStore = useBandTechRidersStore()
const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const isAdding = ref(false)
const panelRef = ref(null)
const rootRef = ref(null)

// The picker has no eye to show what the outline shows, so a hidden section says it in words.
const pickerOptions = computed(() =>
  props.items.map((item) => ({
    id: item.id,
    label: item.is_included ? item.title : `${item.title} (masquée)`
  }))
)

/** After adding or deleting, focus would be left on a control that moved or vanished. */
async function focusPanel() {
  await nextTick()
  if (panelRef.value) {
    panelRef.value.focusHeading()
    return
  }
  // The last section is gone: the add button is what is left to do next. Two exist, one per layout.
  const addButtons = rootRef.value?.querySelectorAll('[data-rider-add]') ?? []
  ;[...addButtons].find((button) => button.offsetParent !== null)?.focus()
}

const ids = computed(() => props.items.map((item) => item.id))
const selectedId = computed(() => selectableItemId(ids.value, route.query.item))
const selectedItem = computed(
  () => props.items.find((item) => item.id === selectedId.value) ?? null
)

function selectItem(itemId) {
  if (!itemId || itemId === route.query.item) return Promise.resolve()
  return router.replace({ query: { ...route.query, item: itemId } })
}

function showError(summary, error) {
  toast.add({ severity: 'error', summary, detail: error.message, life: 5000 })
}

async function handleAdd(type) {
  isAdding.value = true
  try {
    const created = await techRidersStore.createItem(props.bandSpaceId, props.riderId, {
      title: type.label,
      type: type.value
    })
    // Once the route has moved on, so focus lands on the heading already showing the new title.
    await selectItem(created.id)
    focusPanel()
  } catch (e) {
    showError('Erreur', e)
  } finally {
    isAdding.value = false
  }
}

async function handleReorder(orderedIds) {
  try {
    await techRidersStore.reorderItems(props.bandSpaceId, props.riderId, orderedIds)
  } catch (e) {
    showError('Réordonnancement impossible', e)
  }
}

function handleMove(itemId, step) {
  const ordered = movedIds(ids.value, itemId, step)
  if (ordered) handleReorder(ordered)
}

async function handleToggleIncluded(item) {
  try {
    await techRidersStore.setItemIncluded(
      props.bandSpaceId,
      props.riderId,
      item.id,
      !item.is_included
    )
  } catch (e) {
    showError('Erreur', e)
  }
}

function confirmDelete(item) {
  confirm.require({
    message: `« ${item.title} » et son contenu seront supprimés. Cette action est irréversible.`,
    header: 'Supprimer la section',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    acceptProps: { severity: 'danger' },
    accept: async () => {
      // Read before the delete: the store drops the item, and with it the order to pick a neighbour in.
      const next = neighbourAfterRemoval(ids.value, item.id)
      try {
        await techRidersStore.deleteItem(props.bandSpaceId, props.riderId, item.id)
        if (next) await selectItem(next)
        focusPanel()
        toast.add({ severity: 'success', summary: 'Section supprimée', life: 2500 })
      } catch (e) {
        showError('Erreur', e)
      }
    }
  })
}
</script>
