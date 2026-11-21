<template>
  <NewTitleDialog
    v-model:visible="visible"
    :header="t('band_space_setlist.new_song_dialog.header')"
    :label="t('band_space_setlist.new_song_dialog.title_label')"
    :hint="files.length > 0 ? t('band_space_setlist.new_song_dialog.title_from_file') : t('band_space_setlist.new_song_dialog.hint')"
    :required-message="t('band_space_setlist.new_song_dialog.title_required')"
    :fallback-title="() => titleFromFirstFile(files)"
    :create="createWithFiles"
    @created="emit('created', $event)"
  >
    <!-- Files come along at creation (#1431): the tab, a demo, the chart. Each becomes an attachment. -->
    <div class="flex flex-col gap-1.5">
      <span class="text-sm font-medium">
        {{ t('band_space_setlist.new_song_dialog.files_label') }}
        <span class="font-normal text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.new_song_dialog.optional') }}</span>
      </span>
      <button
        type="button"
        :class="[
          'rounded-lg border-2 border-dashed px-3 py-4 text-center text-sm transition-colors',
          isDragging ? 'border-primary bg-primary-50 dark:bg-primary-950' : 'border-surface-300 hover:bg-surface-50 dark:border-surface-600 dark:hover:bg-surface-800'
        ]"
        @click="picker?.click()"
        @dragover.prevent="isDragging = true"
        @dragleave.prevent="isDragging = false"
        @drop.prevent="handleDrop"
      >
        <i class="pi pi-cloud-upload mr-1.5 text-surface-500" aria-hidden="true" />{{ t('band_space_setlist.new_song_dialog.files_drop') }}
      </button>
      <input ref="picker" type="file" multiple class="hidden" @change="handlePicked" />
      <ul v-if="files.length > 0" class="m-0 flex list-none flex-col gap-1 p-0">
        <li v-for="(file, index) in files" :key="`${file.name}-${index}`" class="flex items-center gap-2 rounded-md border border-surface-200 px-2 py-1 text-sm dark:border-surface-700">
          <i :class="[fileIconClass(file.type, file.name), 'text-surface-500']" aria-hidden="true" />
          <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
          <span class="text-xs tabular-nums text-surface-500">{{ formatBytes(file.size) }}</span>
          <Button
            icon="pi pi-times"
            text
            rounded
            size="small"
            severity="secondary"
            :aria-label="t('band_space_setlist.new_song_dialog.remove_file', { name: file.name })"
            @click="files.splice(index, 1)"
          />
        </li>
      </ul>
    </div>
  </NewTitleDialog>
</template>

<script setup>
import Button from 'primevue/button'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { fileIconClass } from '../../../utils/fileType.js'
import { formatBytes } from '../../../utils/formatBytes.js'
import { titleFromFirstFile } from '../../../utils/newSongTitle.js'
import { holdSongFiles } from '../../../utils/pendingSongFiles.js'
import NewTitleDialog from './NewTitleDialog.vue'

/**
 * A song starts from its title (#1067), and may bring its files along (#1431). They are held for the
 * song's page, which the caller opens, and which uploads them where their progress can be followed.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['created'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const songsStore = useBandSongsStore()
const picker = ref(null)
const files = ref([])
const isDragging = ref(false)

watch(visible, (open) => {
  if (!open) files.value = []
})

function handlePicked(event) {
  files.value = [...files.value, ...(event.target.files ?? [])]
  event.target.value = ''
}

function handleDrop(event) {
  isDragging.value = false
  files.value = [...files.value, ...(event.dataTransfer?.files ?? [])]
}

async function createWithFiles(title) {
  const song = await songsStore.createSong(props.bandSpaceId, { title })
  holdSongFiles(song.id, files.value)
  return song
}
</script>
