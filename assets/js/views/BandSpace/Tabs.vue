<template>
  <div>
    <div
      v-if="tabsStore.loadError"
      class="flex flex-col items-center justify-center min-h-[400px] p-8 gap-4"
    >
      <Message severity="error" :closable="false">{{ tabsStore.loadError }}</Message>
      <Button :label="t('band_space_files.page.retry')" icon="pi pi-refresh" severity="secondary" @click="loadAll" />
    </div>

    <section
      v-else
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700"
      :class="{ 'mb-24': isBelowMd && hasTabs }"
    >
      <div v-if="tabsStore.isLoading && !hasTabs" class="flex flex-col gap-2">
        <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
      </div>

      <div v-else-if="!hasTabs" class="flex flex-col items-center text-center gap-3 px-4 py-10">
        <span
          class="w-14 h-14 rounded-2xl flex items-center justify-center bg-surface-100 dark:bg-surface-800 text-primary"
          aria-hidden="true"
        >
          <i class="pi pi-align-justify text-2xl"></i>
        </span>
        <h2 class="m-0 text-lg font-semibold">{{ t('band_space_tabs.list.empty_title') }}</h2>
        <p class="m-0 max-w-md text-sm text-surface-600 dark:text-surface-300">
          {{ t('band_space_tabs.list.empty_intro') }}
        </p>
        <Button
          :label="t('band_space_tabs.list.add')"
          icon="pi pi-plus"
          :loading="isCheckingName"
          @click="pickFile"
        />
      </div>

      <template v-else>
        <div
          class="flex items-center justify-between gap-3 pb-3 mb-1 border-b border-surface-100 dark:border-surface-800"
        >
          <p class="m-0 text-xs text-surface-600 dark:text-surface-300 tabular-nums" aria-live="polite">
            {{ t('band_space_tabs.list.count', tabsStore.totalTabs) }}
          </p>
          <Button
            v-if="!isBelowMd"
            :label="t('band_space_tabs.list.add')"
            icon="pi pi-plus"
            size="small"
            :loading="isCheckingName"
            @click="pickFile"
          />
        </div>

        <TabList
          :band-space-id="bandSpaceId"
          :tabs="tabsStore.tabs"
          :virtual-folders="filesStore.virtualFolders"
          :compact="isBelowMd"
        />

        <div v-if="tabsStore.hasMore" class="flex flex-col items-center gap-2 mt-4">
          <Message v-if="tabsStore.loadMoreError" severity="error" :closable="false">
            {{ tabsStore.loadMoreError }}
          </Message>
          <Button
            :label="t('band_space_files.page.load_more')"
            :loading="tabsStore.isLoadingMore"
            severity="secondary"
            outlined
            @click="tabsStore.fetchMoreTabs(bandSpaceId)"
          />
        </div>
      </template>
    </section>

    <!-- Below md the button floats where the thumb is, as « Importer » does in Files. -->
    <Button
      v-if="isBelowMd && hasTabs"
      :label="t('band_space_tabs.list.add')"
      icon="pi pi-plus"
      rounded
      :loading="isCheckingName"
      class="fixed! right-5 bottom-[calc(1.5rem+env(safe-area-inset-bottom))] z-10 shadow-lg"
      @click="pickFile"
    />

    <!-- No accept filter: phone pickers filter by mime type, and a Guitar Pro file has none, so they
         would grey every tab out. The name is checked once picked. -->
    <input ref="fileInput" type="file" class="hidden" @change="handleFilePicked" />

    <TabSameNameDialog
      v-if="sameNameTab"
      v-model:visible="sameNameDialogVisible"
      :name="sameNameTab.original_name"
      :folder="sameNameFolder"
      @new-version="newVersionDialogVisible = true"
      @separate="openUploadDialog"
    />

    <FileUploadDialog
      v-if="bandSpaceId"
      v-model:visible="uploadDialogVisible"
      :band-space-id="bandSpaceId"
      :initial-files="pickedFile ? [pickedFile] : []"
      :initial-folder-id="proposedFolderId"
      @saved="handleUploadSaved"
      @after-hide="openUploadedTab"
    />

    <FileNewVersionDialog
      v-if="bandSpaceId && sameNameTab"
      v-model:visible="newVersionDialogVisible"
      :band-space-id="bandSpaceId"
      :file-id="sameNameTab.id"
      :initial-file="pickedFile"
      @uploaded="handleVersionUploaded"
      @after-hide="openUploadedTab"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import FileNewVersionDialog from '../../components/BandSpace/Files/FileNewVersionDialog.vue'
import FileUploadDialog from '../../components/BandSpace/Files/FileUploadDialog.vue'
import TabList from '../../components/BandSpace/Tabs/TabList.vue'
import TabSameNameDialog from '../../components/BandSpace/Tabs/TabSameNameDialog.vue'
import { useBandSpaceLiveRefresh } from '../../composables/useBandSpaceLiveRefresh.js'
import { useFileUploadToasts } from '../../composables/useFileUploadToasts.js'
import { useIsBelowMd } from '../../composables/useIsPhone.js'
import { BAND_SPACE_ROUTES } from '../../constants/bandSpace.js'
import { useBandFilesStore } from '../../store/bandSpace/bandSpaceFiles.js'
import { useBandTabsStore } from '../../store/bandSpace/bandSpaceTabs.js'
import { fileLocation } from '../../utils/fileListing.js'
import { isGuitarProName } from '../../utils/guitarPro.js'
import { firstUploadedTab } from '../../utils/tabList.js'
import { rememberedTabFolder, rememberTabFolder } from '../../utils/tabUploadFolder.js'

/**
 * « Tablatures » (#1430): every Guitar Pro file of the space, whatever its folder. A view over Files,
 * never a second storage: a tab is a file, with its folder, versions, trash and quota.
 */
const route = useRoute()
const router = useRouter()
const toast = useToast()
const { t } = useI18n()
const tabsStore = useBandTabsStore()
// The list places unfiled attachments with the files store's virtual folders, and the upload dialogs read
// the folder tree, tags and quota from it.
const filesStore = useBandFilesStore()
const { announceUpload, announceVersion } = useFileUploadToasts()
const isBelowMd = useIsBelowMd()

// The :key on <router-view> remounts this view on a space switch, but Pinia keeps the last space's data.
tabsStore.clear()
filesStore.clear()

const bandSpaceId = computed(() => route.params.id)
const hasTabs = computed(() => tabsStore.tabs.length > 0)

const fileInput = ref(null)
const pickedFile = ref(null)
const isCheckingName = ref(false)
/** The tab already carrying the picked file's name, offered a new version. */
const sameNameTab = ref(null)
const sameNameFolder = computed(() =>
  sameNameTab.value ? fileLocation(sameNameTab.value, filesStore.virtualFolders).label : ''
)
const proposedFolderId = ref(null)
/** Opened once its dialog is gone, see openUploadedTab. */
const uploadedTab = ref(null)

const sameNameDialogVisible = ref(false)
const uploadDialogVisible = ref(false)
const newVersionDialogVisible = ref(false)

onMounted(loadAll)

onUnmounted(() => {
  tabsStore.clear()
  filesStore.clear()
})

useBandSpaceLiveRefresh({
  bandSpaceId,
  modules: ['file'],
  refresh: () => {
    tabsStore.refreshFromLiveSignal(bandSpaceId.value)
    filesStore.fetchFolders(bandSpaceId.value)
  }
})

function loadAll() {
  if (!bandSpaceId.value) return
  tabsStore.fetchTabs(bandSpaceId.value)
  filesStore.fetchFolders(bandSpaceId.value)
}

function pickFile() {
  fileInput.value?.click()
}

async function handleFilePicked(event) {
  const file = event.target.files?.[0]
  // Cleared so picking the very same file again still fires a change event.
  event.target.value = ''
  if (!file) return

  if (!isGuitarProName(file.name)) {
    toast.add({
      severity: 'warn',
      summary: t('band_space_tabs.list.not_guitar_pro'),
      detail: t('band_space_tabs.list.not_guitar_pro_detail', { name: file.name }),
      life: 6000
    })
    return
  }

  pickedFile.value = file
  isCheckingName.value = true
  const [sameName] = await Promise.all([
    // The check only spares the history a second file: without it the upload still goes through.
    tabsStore.findTabNamed(bandSpaceId.value, file.name).catch(() => null),
    // What the upload dialogs read, loaded when one is about to open rather than on every visit.
    filesStore.fetchFolders(bandSpaceId.value),
    filesStore.fetchTags(bandSpaceId.value),
    filesStore.fetchQuota(bandSpaceId.value)
  ])
  sameNameTab.value = sameName
  isCheckingName.value = false

  if (sameNameTab.value) {
    sameNameDialogVisible.value = true
    return
  }
  openUploadDialog()
}

function openUploadDialog() {
  proposedFolderId.value = rememberedTabFolder(bandSpaceId.value, filesStore.folders)
  uploadDialogVisible.value = true
}

function handleUploadSaved(result) {
  announceUpload(result)
  if (result.uploadedCount > 0) {
    rememberTabFolder(bandSpaceId.value, result.folderId)
  }
  uploadedTab.value = firstUploadedTab(result.uploadedFiles)
  // The page stays only when no tab was stored; otherwise the reader opens and the list reloads on return.
  if (!uploadedTab.value) {
    tabsStore.fetchTabs(bandSpaceId.value, { quiet: true })
  }
}

function handleVersionUploaded(result) {
  announceVersion(result)
  uploadedTab.value = sameNameTab.value
}

/** The reader opens on the new tab once its dialog has closed, so the dialog takes its scroll lock along. */
function openUploadedTab() {
  const tab = uploadedTab.value
  uploadedTab.value = null
  if (!tab) return

  router.push({
    name: BAND_SPACE_ROUTES.TAB_READER,
    params: { id: bandSpaceId.value, fileId: tab.id }
  })
}
</script>
