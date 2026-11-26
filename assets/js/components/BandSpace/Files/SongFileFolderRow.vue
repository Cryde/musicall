<template>
  <!-- Lights up under a drag; the drop itself is the tree's, which knows this row stands for the folder. -->
  <div
    :class="[
      'flex items-center gap-1 rounded-xl border pl-1 pr-1.5 transition-colors',
      isDropTarget
        ? 'border-primary bg-primary-50 dark:bg-primary-950'
        : 'border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900'
    ]"
    @dragenter.prevent="handleDragEnter"
    @dragover.prevent
    @dragleave="handleDragLeave"
    @drop="clearDropTarget"
  >
    <button
      type="button"
      class="flex min-h-11 min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-lg border-0 bg-transparent px-1.5 text-left"
      :aria-expanded="isOpen"
      @click="emit('toggle', node.folder.id)"
    >
      <i :class="['pi text-xs text-surface-500 dark:text-surface-400', isOpen ? 'pi-chevron-down' : 'pi-chevron-right']" aria-hidden="true" />
      <i :class="['pi text-amber-600 dark:text-amber-400', isOpen ? 'pi-folder-open' : 'pi-folder']" aria-hidden="true" />
      <span class="truncate text-sm font-semibold text-surface-900 dark:text-surface-0">{{ node.folder.name }}</span>
      <span class="shrink-0 text-xs tabular-nums text-surface-600 dark:text-surface-300">
        {{ t('band_space_files.count.files', { count: node.fileCount }, node.fileCount) }}
      </span>
    </button>
    <template v-if="canEdit">
      <Button
        icon="pi pi-ellipsis-v"
        text
        rounded
        severity="secondary"
        size="small"
        class="shrink-0"
        aria-haspopup="true"
        :aria-label="t('band_space_files.song_folders.actions', { name: node.folder.name })"
        @click="menu?.toggle($event)"
      />
      <Menu ref="menu" :model="menuItems" :pt="DANGER_MENU_PT" popup />
    </template>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { DANGER_MENU_PT } from '../../../constants/dangerMenu.js'
import { SONG_FILE_DRAG_TYPE } from '../../../constants/songFileDrag.js'
import { canHoldSongSubfolder } from '../../../utils/songFileFolders.js'

/** One folder of a song's files (#1451): opens and closes, shows where a drag would land, and its ⋮. */
const props = defineProps({
  /** A node of buildSongFileTree(). */
  node: { type: Object, required: true },
  isOpen: { type: Boolean, default: false },
  canEdit: { type: Boolean, default: false }
})

const emit = defineEmits(['toggle', 'upload', 'link', 'create-subfolder', 'rename', 'delete'])

const { t } = useI18n()
const menu = ref(null)
const isDropTarget = ref(false)
// dragenter and dragleave fire for every child crossed: the count says when the pointer really left.
let dragDepth = 0

function accepts(event) {
  const types = event.dataTransfer?.types ?? []
  return props.canEdit && (types.includes('Files') || types.includes(SONG_FILE_DRAG_TYPE))
}

function handleDragEnter(event) {
  if (!accepts(event)) return
  dragDepth += 1
  isDropTarget.value = true
}

function handleDragLeave() {
  if (!isDropTarget.value) return
  dragDepth = Math.max(0, dragDepth - 1)
  if (dragDepth === 0) isDropTarget.value = false
}

function clearDropTarget() {
  dragDepth = 0
  isDropTarget.value = false
}

const menuItems = computed(() => {
  const folder = props.node.folder
  const items = [
    {
      label: t('band_space_files.song_folders.upload_here'),
      icon: 'pi pi-cloud-upload',
      command: () => emit('upload', folder.id)
    },
    {
      label: t('band_space_files.attached_section.choose'),
      icon: 'pi pi-link',
      command: () => emit('link', folder.id)
    }
  ]
  if (canHoldSongSubfolder(props.node.depth)) {
    items.push({
      label: t('band_space_files.folder_edit.title_create_sub'),
      icon: 'pi pi-folder-plus',
      command: () => emit('create-subfolder', folder)
    })
  }
  items.push(
    {
      label: t('band_space_files.song_folders.rename'),
      icon: 'pi pi-pencil',
      command: () => emit('rename', folder)
    },
    {
      label: t('band_space_files.song_folders.delete'),
      icon: 'pi pi-trash',
      danger: true,
      command: () => emit('delete', folder)
    }
  )
  return items
})
</script>
