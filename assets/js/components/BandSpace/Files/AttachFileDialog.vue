<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('band_space_files.attach_dialog.title')"
    :style="{ width: '36rem' }"
    @hide="resetState"
  >
    <div class="flex flex-col gap-3">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText
          v-model="searchQuery"
          :placeholder="t('band_space_files.attach_dialog.search_placeholder')"
          :aria-label="t('band_space_files.attach_dialog.search_placeholder')"
          size="small"
          class="w-full"
          @input="searchFiles"
        />
      </IconField>
      <div v-if="isSearching" class="flex flex-col gap-2">
        <Skeleton v-for="i in 3" :key="i" width="100%" height="2.5rem" border-radius="0.5rem" />
      </div>
      <p v-else-if="searchResults.length === 0" class="text-xs italic text-surface-400 text-center py-4">
        {{ t('band_space_files.attach_dialog.no_files') }}
        <span class="block">{{ t('band_space_files.attach_dialog.manual_only') }}</span>
      </p>
      <button
        v-for="file in searchResults"
        :key="file.id"
        type="button"
        class="flex items-center gap-2 p-2 rounded-lg border border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800 text-left"
        :disabled="isAttaching"
        @click="attachExisting(file)"
      >
        <i :class="fileIconClass(file.mime_type, file.original_name)" class="text-surface-500" aria-hidden="true"></i>
        <span class="text-sm font-medium truncate flex-1">{{ file.original_name }}</span>
        <span class="text-xs text-surface-400 tabular-nums">{{ formatBytes(file.size) }}</span>
      </button>
      <Message v-if="attachError" severity="error" :closable="false">{{ attachError }}</Message>
    </div>
    <template #footer>
      <Button :label="t('band_space_files.common.cancel')" severity="secondary" text @click="visible = false" />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { fileIconClass } from '../../../utils/fileType.js'
import { formatBytes } from '../../../utils/formatBytes.js'

/**
 * « Choisir dans Fichiers »: links a file already in Files to a song, a setlist, a task or a finance
 * entry. The file stays in its folder. Only files attached to nothing are offered.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  sourceType: { type: String, required: true },
  sourceId: { type: String, required: true }
})

const emit = defineEmits(['attached'])
const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const toast = useToast()

const searchQuery = ref('')
const searchResults = ref([])
const isSearching = ref(false)
const isAttaching = ref(false)
const attachError = ref(null)
let searchDebounce = null

watch(visible, (open) => {
  if (open) doSearch()
})

function searchFiles() {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(doSearch, 250)
}

async function doSearch() {
  isSearching.value = true
  try {
    const data = await bandSpaceFilesApi.getFiles(props.bandSpaceId, {
      query: searchQuery.value.trim() || undefined,
      source: 'manual'
    })
    searchResults.value = data.member ?? []
  } catch {
    searchResults.value = []
  } finally {
    isSearching.value = false
  }
}

async function attachExisting(file) {
  attachError.value = null
  isAttaching.value = true
  try {
    const updated = await bandSpaceFilesApi.attachExistingFile(
      props.bandSpaceId,
      file.id,
      props.sourceType,
      props.sourceId
    )
    emit('attached', updated)
    toast.add({
      severity: 'success',
      summary: t('band_space_files.attach_dialog.attached'),
      life: 3000
    })
    visible.value = false
  } catch (e) {
    attachError.value = e.message
  } finally {
    isAttaching.value = false
  }
}

function resetState() {
  searchQuery.value = ''
  searchResults.value = []
  attachError.value = null
}
</script>
