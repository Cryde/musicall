<template>
  <section
    class="flex flex-col gap-4 min-w-0"
    :aria-label="`Édition : ${item.title}`"
  >
    <div class="flex flex-wrap items-center gap-3">
      <i :class="['pi', riderSectionTypeIcon(item.type), 'text-surface-600 dark:text-surface-300']" aria-hidden="true" />
      <!-- Focusable from script only: after adding or deleting a section, focus lands here so it is
           not left on a control that just vanished. -->
      <h2 ref="headingRef" tabindex="-1" class="text-xl font-bold min-w-0 truncate outline-none focus-visible:ring-2 focus-visible:ring-primary rounded">{{ item.title }}</h2>
      <span class="flex-1" />

      <span class="text-sm text-surface-600 dark:text-surface-300" aria-live="polite">
        <template v-if="saveState === 'error'">
          <i class="pi pi-times mr-1 text-red-600 dark:text-red-400" aria-hidden="true" />{{ saveMessage }}
        </template>
      </span>

      <template v-if="!readOnly">
        <div class="flex items-center gap-2">
          <ToggleSwitch
            :input-id="visibilityId"
            :model-value="item.is_included"
            @update:model-value="emit('toggle-included', item)"
          />
          <label :for="visibilityId" class="text-sm">Visible dans le PDF</label>
        </div>
        <Button
          icon="pi pi-ellipsis-v"
          severity="secondary"
          text
          rounded
          aria-haspopup="menu"
          :aria-label="`Actions de la section ${item.title}`"
          @click="(event) => menuRef.toggle(event)"
        />
        <Menu ref="menuRef" :model="menuItems" :popup="true" />
      </template>
      <span
        v-else-if="!item.is_included"
        class="text-sm text-surface-600 dark:text-surface-300"
      >
        Masquée dans le PDF
      </span>
    </div>

    <!-- Keyed per section: switching section unmounts the previous editor, which flushes its
         pending edit on the way out. The panel itself stays mounted, so that last save still has
         somewhere to report to. -->
    <RiderTextItemEditor
      v-if="item.type === 'text'"
      :key="item.id"
      :item-id="item.id"
      :title="item.title"
      :content="item.content"
      :read-only="readOnly"
      @save="handleSave"
    />
    <RiderDocumentItemEditor
      v-else-if="item.type === 'document'"
      :key="item.id"
      :band-space-id="bandSpaceId"
      :item-id="item.id"
      :file="item.file"
      :read-only="readOnly"
      @choose="handleChooseFile"
    />
    <RiderContactsItemEditor
      v-else-if="item.type === 'contacts'"
      :key="item.id"
      :band-space-id="bandSpaceId"
      :item-id="item.id"
      :title="item.title"
      :contacts="item.contacts"
      :content="item.content"
      :read-only="readOnly"
      @save="handleSave"
    />
    <RiderStagePlotEditor
      v-else-if="item.type === 'stage_plot'"
      :key="item.id"
      :band-space-id="bandSpaceId"
      :rider-id="riderId"
      :item-id="item.id"
      :content="item.content"
      :read-only="readOnly"
    />
    <RiderPatchListEditor
      v-else-if="item.type === 'patch_list'"
      :key="item.id"
      :band-space-id="bandSpaceId"
      :rider-id="riderId"
      :item-id="item.id"
      :patch-list="item.patch_list"
      :read-only="readOnly"
    />

    <Dialog v-model:visible="renameDialogOpen" modal header="Renommer la section" :style="{ width: '26rem' }">
      <form class="flex flex-col gap-4" @submit.prevent="handleRename">
        <div>
          <label for="renameSectionTitle" class="block text-sm font-medium mb-1">
            Titre <span class="text-red-600 dark:text-red-400">*</span>
          </label>
          <InputText id="renameSectionTitle" v-model="renameTitle" autofocus class="w-full" />
        </div>
        <div class="flex justify-end gap-2">
          <Button label="Annuler" severity="secondary" text type="button" @click="renameDialogOpen = false" />
          <Button label="Renommer" type="submit" :loading="isRenaming" :disabled="!renameTitle.trim()" />
        </div>
      </form>
    </Dialog>
  </section>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Menu from 'primevue/menu'
import ToggleSwitch from 'primevue/toggleswitch'
import { useToast } from 'primevue/usetoast'
import { computed, onBeforeUnmount, ref, useId } from 'vue'
import { riderSectionTypeIcon } from '../../../constants/techRider.js'
import { useBandTechRidersStore } from '../../../store/bandSpace/bandSpaceTechRiders.js'
import RiderContactsItemEditor from './RiderContactsItemEditor.vue'
import RiderDocumentItemEditor from './RiderDocumentItemEditor.vue'
import RiderPatchListEditor from './RiderPatchListEditor.vue'
import RiderStagePlotEditor from './RiderStagePlotEditor.vue'
import RiderTextItemEditor from './RiderTextItemEditor.vue'

/**
 * The selected section of a tech rider (#1091): its header (title, visibility, actions), then the
 * editor of its type. One section at a time, so the page reads as a document being written rather
 * than an accordion of every form at once.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  riderId: { type: String, required: true },
  item: { type: Object, required: true },
  readOnly: { type: Boolean, default: false },
  canMoveUp: { type: Boolean, default: false },
  canMoveDown: { type: Boolean, default: false }
})

const emit = defineEmits(['move', 'delete', 'toggle-included'])

const techRidersStore = useBandTechRidersStore()
const toast = useToast()
const visibilityId = useId()

const menuRef = ref(null)
const renameDialogOpen = ref(false)
const renameTitle = ref('')
const isRenaming = ref(false)

// Not reactive: only read to decide when the last save of an item is back.
const savesInFlight = {}
// The latest save issued per item. Only that one may report an error: an older save failing after
// a newer one went through would otherwise offer « Réessayer » with content the server has replaced.
const latestSave = {}
const headingRef = ref(null)

defineExpose({ focusHeading: () => headingRef.value?.focus() })
let isMounted = true
onBeforeUnmount(() => {
  isMounted = false
})

const saveState = computed(() => techRidersStore.saveStateFor(props.item.id)?.state ?? null)
const saveMessage = computed(() => techRidersStore.saveStateFor(props.item.id)?.message ?? 'Erreur')

const menuItems = computed(() => [
  { label: 'Renommer', icon: 'pi pi-pencil', command: openRename },
  {
    label: 'Monter',
    icon: 'pi pi-arrow-up',
    disabled: !props.canMoveUp,
    command: () => emit('move', -1)
  },
  {
    label: 'Descendre',
    icon: 'pi pi-arrow-down',
    disabled: !props.canMoveDown,
    command: () => emit('move', 1)
  },
  { separator: true },
  // Asked one level up: the workspace knows the order, so it knows which section to show next.
  {
    label: 'Supprimer',
    icon: 'pi pi-trash',
    class: 'text-red-600 dark:text-red-400',
    command: () => emit('delete')
  }
])

async function handleChooseFile({ itemId, fileId }) {
  try {
    await techRidersStore.setItemFile(props.bandSpaceId, props.riderId, itemId, fileId)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  }
}

/**
 * An autosave failure is easy to miss, so the status shows the reason rather than merely
 * ceasing to say saved. The server's own message is used, which is how the content size cap
 * reaches the user.
 */
async function handleSave(save) {
  const { itemId, content, isSideWrite = false } = save
  // The contacts e-mails switch saves while a note may still be waiting on its debounce. Its save
  // must not report the item as saved over that note, so it leaves a pending state as it is.
  const reportsProgress = !(
    isSideWrite && techRidersStore.saveStateFor(itemId)?.state === 'pending'
  )
  if (reportsProgress) report(itemId, 'saving')
  savesInFlight[itemId] = (savesInFlight[itemId] ?? 0) + 1
  const sequence = (latestSave[itemId] ?? 0) + 1
  latestSave[itemId] = sequence
  try {
    await techRidersStore.saveItemContent(props.bandSpaceId, props.riderId, itemId, content)
    savesInFlight[itemId] -= 1
    // Saved only once the last save of this item is back and nothing was typed meanwhile: the text
    // editor marks new typing as pending, and a contacts item has two writers whose saves overlap.
    if (savesInFlight[itemId] === 0 && techRidersStore.saveStateFor(itemId)?.state === 'saving') {
      report(itemId, 'saved')
    }
  } catch (e) {
    savesInFlight[itemId] -= 1
    if (sequence !== latestSave[itemId]) return
    report(
      itemId,
      'error',
      e.violationsByField?.content?.[0]?.message ?? e.message ?? 'Erreur',
      () => handleSave(save)
    )
  }
}

/**
 * The panel stays mounted while sections are switched, so the last save of the section just left
 * still reports here. Once the page itself goes, a state written would describe nothing on screen.
 */
function report(itemId, state, message = null, retry = null) {
  if (isMounted) techRidersStore.setItemSaveState(itemId, state, message, retry)
}

function openRename() {
  renameTitle.value = props.item.title
  renameDialogOpen.value = true
}

async function handleRename() {
  const title = renameTitle.value.trim()
  if (!title) return

  isRenaming.value = true
  try {
    await techRidersStore.renameItem(props.bandSpaceId, props.riderId, props.item.id, title)
    renameDialogOpen.value = false
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isRenaming.value = false
  }
}
</script>
