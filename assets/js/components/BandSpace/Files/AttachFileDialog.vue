<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('band_space_files.attach_dialog.title')"
    :style="{ width: '36rem' }"
    :closable="!isUploading"
    :close-on-escape="!isUploading"
    @hide="resetState"
  >
    <Tabs v-model:value="activeTab">
      <TabList>
        <Tab value="upload">{{ t('band_space_files.attach_dialog.tab_upload') }}</Tab>
        <Tab value="existing">{{ t('band_space_files.attach_dialog.tab_existing') }}</Tab>
      </TabList>

      <TabPanels>
        <TabPanel value="upload">
          <div class="flex flex-col gap-4 pt-2">
            <div
              class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer transition-colors"
              :class="dropZoneClasses"
              @click="triggerFilePicker"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @drop.prevent="handleDrop"
            >
              <input ref="fileInput" type="file" class="hidden" @change="handleFileChange" />

              <template v-if="!selectedFile">
                <i class="pi pi-cloud-upload text-3xl text-surface-400 mb-2"></i>
                <i18n-t
                  keypath="band_space_files.attach_dialog.drop_zone"
                  tag="p"
                  class="text-sm text-surface-600 dark:text-surface-300"
                >
                  <template #browse>
                    <span class="text-primary-500 underline">{{ t('band_space_files.common.browse') }}</span>
                  </template>
                </i18n-t>
              </template>

              <template v-else>
                <div class="flex items-center justify-center gap-2">
                  <i class="pi pi-file text-2xl text-surface-500"></i>
                  <div class="text-left min-w-0">
                    <p class="text-sm font-medium truncate">{{ selectedFile.name }}</p>
                    <p class="text-xs text-surface-400">{{ formatBytes(selectedFile.size) }}</p>
                  </div>
                  <Button
                    icon="pi pi-times"
                    :aria-label="t('band_space_files.common.remove_file')"
                    text
                    rounded
                    size="small"
                    :disabled="isUploading"
                    @click.stop="clearFile"
                  />
                </div>
              </template>
            </div>

            <div v-if="isUploading" class="flex flex-col gap-1">
              <ProgressBar :value="uploadProgress" />
              <p class="text-xs text-surface-500 text-center">{{ t('band_space_files.common.upload_progress', { percent: uploadProgress }) }}</p>
            </div>

            <Message v-if="uploadError" severity="error" :closable="false">{{ uploadError }}</Message>
          </div>
        </TabPanel>

        <TabPanel value="existing">
          <div class="flex flex-col gap-3 pt-2">
            <IconField>
              <InputIcon class="pi pi-search" />
              <InputText
                v-model="searchQuery"
                :placeholder="t('band_space_files.attach_dialog.search_placeholder')"
                size="small"
                class="w-full"
                @input="searchFiles"
              />
            </IconField>

            <div v-if="isSearching" class="flex flex-col gap-2">
              <Skeleton v-for="i in 3" :key="i" width="100%" height="2.5rem" borderRadius="0.5rem" />
            </div>

            <p v-else-if="searchResults.length === 0" class="text-xs italic text-surface-400 text-center py-4">
              {{ t('band_space_files.attach_dialog.no_files') }}
              <span class="block">{{ t('band_space_files.attach_dialog.manual_only') }}</span>
            </p>

            <div
              v-for="file in searchResults"
              :key="file.id"
              class="flex items-center gap-2 p-2 rounded-lg border border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800 cursor-pointer"
              @click="attachExisting(file)"
            >
              <i :class="iconForMime(file.mime_type)" class="text-surface-500"></i>
              <span class="text-sm font-medium truncate flex-1">{{ file.original_name }}</span>
              <span class="text-xs text-surface-400 tabular-nums">{{ formatBytes(file.size) }}</span>
            </div>

            <Message v-if="attachError" severity="error" :closable="false">{{ attachError }}</Message>
          </div>
        </TabPanel>
      </TabPanels>
    </Tabs>

    <template #footer>
      <Button
        :label="t('band_space_files.common.cancel')"
        severity="secondary"
        text
        :disabled="isUploading"
        @click="visible = false"
      />
      <Button
        v-if="activeTab === 'upload'"
        :label="t('band_space_files.attach_dialog.submit')"
        icon="pi pi-cloud-upload"
        :disabled="!selectedFile || isUploading"
        :loading="isUploading"
        @click="handleUpload"
      />
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
import ProgressBar from 'primevue/progressbar'
import Skeleton from 'primevue/skeleton'
import Tab from 'primevue/tab'
import TabList from 'primevue/tablist'
import TabPanel from 'primevue/tabpanel'
import TabPanels from 'primevue/tabpanels'
import Tabs from 'primevue/tabs'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { formatBytes } from '../../../utils/formatBytes.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  sourceType: { type: String, required: true },
  sourceId: { type: String, required: true }
})

const emit = defineEmits(['attached'])

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const toast = useToast()

const activeTab = ref('upload')

// Upload state
const fileInput = ref(null)
const isDragging = ref(false)
const selectedFile = ref(null)
const isUploading = ref(false)
const uploadProgress = ref(0)
const uploadError = ref(null)

// Pick-existing state
const searchQuery = ref('')
const searchResults = ref([])
const isSearching = ref(false)
const attachError = ref(null)
let searchDebounce = null

const dropZoneClasses = computed(() => {
  if (isDragging.value) {
    return 'border-primary-500 bg-primary-50 dark:bg-primary-950'
  }
  return 'border-surface-300 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800'
})

watch(
  [visible, activeTab],
  ([open, tab]) => {
    if (open && tab === 'existing') {
      doSearch()
    }
  },
  { immediate: true }
)

function triggerFilePicker() {
  if (isUploading.value) return
  fileInput.value?.click()
}

function handleFileChange(event) {
  const file = event.target.files?.[0]
  if (file) selectedFile.value = file
}

function handleDrop(event) {
  isDragging.value = false
  const file = event.dataTransfer?.files?.[0]
  if (file) selectedFile.value = file
}

function clearFile() {
  selectedFile.value = null
  if (fileInput.value) fileInput.value.value = ''
}

async function handleUpload() {
  if (!selectedFile.value) return
  isUploading.value = true
  uploadProgress.value = 0
  uploadError.value = null

  try {
    const result = await bandSpaceFilesApi.attachFileUpload(
      props.bandSpaceId,
      props.sourceType,
      props.sourceId,
      selectedFile.value,
      (percent) => {
        uploadProgress.value = percent
      }
    )
    emit('attached', result.file)
    toast.add({
      severity: 'success',
      summary: t('band_space_files.attach_dialog.attached'),
      life: 3000
    })
    if (result.quotaApproaching) {
      toast.add({
        severity: 'warn',
        summary: t('band_space_files.common.quota_approaching.summary'),
        detail: t('band_space_files.common.quota_approaching.detail'),
        life: 6000
      })
    }
    visible.value = false
  } catch (e) {
    uploadError.value = e.message
  } finally {
    isUploading.value = false
  }
}

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
  }
}

function resetState() {
  selectedFile.value = null
  uploadProgress.value = 0
  uploadError.value = null
  searchQuery.value = ''
  searchResults.value = []
  attachError.value = null
  activeTab.value = 'upload'
  if (fileInput.value) fileInput.value.value = ''
}

function iconForMime(mime) {
  if (!mime) return 'pi pi-file'
  if (mime.startsWith('audio/')) return 'pi pi-volume-up'
  if (mime.startsWith('image/')) return 'pi pi-image'
  if (mime.startsWith('video/')) return 'pi pi-video'
  if (mime === 'application/pdf') return 'pi pi-file-pdf'
  return 'pi pi-file'
}
</script>
