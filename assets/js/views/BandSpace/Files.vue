<template>
  <div>
    <div v-if="filesStore.isLoadingFolders && filesStore.folders.length === 0" class="flex gap-4">
      <div class="w-64 flex flex-col gap-2">
        <Skeleton v-for="i in 5" :key="i" width="100%" height="2rem" borderRadius="0.375rem" />
      </div>
      <div class="flex-1 flex flex-col gap-2">
        <Skeleton width="100%" height="2.5rem" borderRadius="0.5rem" />
        <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
      </div>
    </div>

    <div
      v-else-if="filesStore.loadError"
      class="flex flex-col items-center justify-center min-h-[400px] p-8 gap-4"
    >
      <Message severity="error" :closable="false">{{ filesStore.loadError }}</Message>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="loadAll" />
    </div>

    <div v-else class="flex flex-col lg:flex-row gap-6">
      <aside
        class="w-full lg:w-64 shrink-0 bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700"
      >
        <h2 class="text-sm font-semibold text-surface-700 dark:text-surface-200 px-3 mb-3">
          Dossiers
        </h2>

        <FolderTree
          :folders="filesStore.folders"
          :virtual-folders="filesStore.virtualFolders"
          :active-folder-id="filesStore.activeFolderId"
          :band-space-id="bandSpaceId"
          :is-admin="isAdmin"
          @select="handleFolderSelect"
        />

        <p
          v-if="filesStore.folders.length === 0 && filesStore.virtualFolders.length === 0"
          class="text-xs text-surface-400 italic px-3 py-4"
        >
          Aucun dossier pour l'instant.
        </p>

        <!-- Under the tree rather than in it: neither is a folder. One is every file in the space
             whatever folder it sits in, the other is what has been deleted. -->
        <div
          class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700 flex flex-col gap-1"
        >
          <button
            type="button"
            class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-left transition-colors duration-150"
            :class="
              isAllFilesActive
                ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-200 font-medium'
                : 'text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-800'
            "
            :aria-current="isAllFilesActive ? 'true' : null"
            v-tooltip.top="'Tous les fichiers du space, quel que soit leur dossier'"
            @click="handleFolderSelect(null)"
          >
            <i class="pi pi-list" aria-hidden="true"></i>
            <span class="flex-1 truncate">Tous les fichiers</span>
          </button>

          <button
            type="button"
            class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-left transition-colors duration-150"
            :class="
              isTrashActive
                ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-200 font-medium'
                : 'text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-800'
            "
            :aria-current="isTrashActive ? 'true' : null"
            @click="handleFolderSelect(TRASH_FOLDER_ID)"
          >
            <i class="pi pi-trash" aria-hidden="true"></i>
            <span class="flex-1 truncate">Corbeille</span>
            <span class="text-xs text-surface-500 tabular-nums">{{ filesStore.archivedCount }}</span>
          </button>
        </div>
      </aside>

      <section class="flex-1 flex flex-col gap-4 min-w-0">
        <div
          v-if="showBreadcrumb"
          class="bg-surface-0 dark:bg-surface-900 rounded-2xl px-4 py-3 border border-surface-200 dark:border-surface-700"
        >
          <FolderBreadcrumb
            :folders="filesStore.folders"
            :active-folder-id="filesStore.activeFolderId"
            @select="handleFolderSelect"
          />
        </div>

        <div
          v-if="!isTrashActive"
          class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700"
        >
          <!-- Two rows on purpose: the four filters need about 700px and the two actions about 340px,
               so a single row wraps on any laptop and leaves the sort control orphaned underneath. -->
          <div class="flex flex-col gap-3">
            <div class="flex flex-wrap items-center justify-end gap-2">
              <Button
                label="Nouveau dossier"
                icon="pi pi-folder-plus"
                size="small"
                severity="secondary"
                :disabled="isVirtualFolderActive"
                v-tooltip.top="
                  isVirtualFolderActive
                    ? 'Les dossiers virtuels sont remplis automatiquement par les attachements.'
                    : null
                "
                @click="createFolderDialogVisible = true"
              />
              <Button
                label="Importer un fichier"
                icon="pi pi-cloud-upload"
                size="small"
                :disabled="isVirtualFolderActive"
                v-tooltip.top="
                  isVirtualFolderActive
                    ? 'Les dossiers virtuels sont remplis automatiquement par les attachements.'
                    : null
                "
                @click="uploadDialogVisible = true"
              />
            </div>

            <FileFilterBar
              :filters="filesStore.filters"
              :tags="filesStore.tags"
              @update-filter="handleFilterUpdate"
            />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700">
          <!-- The count and the load more control sit here rather than inside the two lists: both
               page identically, and the trash needs it most, since a file it cannot reach is a file
               app:band-space:purge eventually destroys. -->
          <div
            v-if="filesStore.totalFiles > 0 || isSpaceWideSearch"
            class="flex items-center justify-between gap-3 pb-3 mb-1 border-b border-surface-100 dark:border-surface-800"
          >
            <p
              class="text-xs text-surface-600 dark:text-surface-300 tabular-nums"
              aria-live="polite"
            >
              {{ filesStore.filesCountLabel }}
            </p>
            <div class="flex items-center gap-3">
              <!-- Said out loud because the breadcrumb still names the folder the member came from, and
                   the results deliberately come from outside it. -->
              <span
                v-if="isSpaceWideSearch"
                class="flex items-center gap-2 text-xs text-surface-500 dark:text-surface-400"
              >
                <i class="pi pi-search" aria-hidden="true"></i>
                Recherche dans tout l'espace
              </span>
              <span
                v-if="filesStore.isRefreshingFiles"
                class="flex items-center gap-2 text-xs text-surface-600 dark:text-surface-300"
              >
                <i class="pi pi-spin pi-spinner" aria-hidden="true"></i>
                Actualisation…
              </span>
            </div>
          </div>

          <div
            :class="filesStore.isRefreshingFiles ? 'opacity-50 transition-opacity' : ''"
            :aria-busy="filesStore.isRefreshingFiles ? 'true' : 'false'"
          >
            <FileTrashList
              v-if="isTrashActive"
              :band-space-id="bandSpaceId"
              :files="filesStore.files"
              :is-loading="filesStore.isLoadingFiles"
              :is-admin="isAdmin"
            />
            <FileList
              v-else
              :folders="inlineFolders"
              @open-folder="handleFolderSelect"
              :band-space-id="bandSpaceId"
              :files="filesStore.files"
              :is-loading="filesStore.isLoadingFiles"
              :empty-message="emptyMessage"
              :show-location="showFileLocation"
              @select="handleFileSelect"
              @open-rename="handleOpenRename"
              @open-share="handleOpenShare"
              @open-versions="handleOpenVersions"
              @open-move="handleOpenMove"
              @open-location="handleOpenLocation"
            />
          </div>

          <div v-if="filesStore.hasMoreFiles" class="flex flex-col items-center gap-2 mt-4">
            <Message v-if="filesStore.loadMoreError" severity="error" :closable="false">
              {{ filesStore.loadMoreError }}
            </Message>
            <Button
              label="Charger plus"
              :loading="filesStore.isLoadingMoreFiles"
              severity="secondary"
              outlined
              @click="handleLoadMore"
            />
          </div>
        </div>
      </section>
    </div>

    <FolderEditDialog
      v-if="bandSpaceId && createFolderDialogVisible"
      v-model:visible="createFolderDialogVisible"
      :band-space-id="bandSpaceId"
      :mode="activeRealFolderId ? 'create-sub' : 'create-root'"
      :parent-id="activeRealFolderId"
    />

    <FileUploadDialog
      v-if="bandSpaceId"
      v-model:visible="uploadDialogVisible"
      :band-space-id="bandSpaceId"
      @saved="handleUploadSaved"
    />

    <FileDetailDrawer
      v-if="bandSpaceId"
      v-model:visible="detailVisible"
      :band-space-id="bandSpaceId"
      :auto-start-rename="autoStartRename"
      @close="handleDrawerClose"
      @deleted="handleFileDeleted"
      @share="handleOpenShare"
      @versions="handleOpenVersions"
    />

    <FileShareDialog
      v-if="bandSpaceId && shareDialogFileId"
      v-model:visible="shareDialogVisible"
      :band-space-id="bandSpaceId"
      :file-id="shareDialogFileId"
    />

    <FileVersionPanel
      v-if="bandSpaceId && versionPanelFileId"
      v-model:visible="versionPanelVisible"
      :band-space-id="bandSpaceId"
      :file-id="versionPanelFileId"
      :file-name="versionPanelFileName"
    />

    <FileMoveDialog
      v-if="bandSpaceId && moveDialogFile"
      v-model:visible="moveDialogVisible"
      :band-space-id="bandSpaceId"
      :file="moveDialogFile"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import FileDetailDrawer from '../../components/BandSpace/Files/FileDetailDrawer.vue'
import FileFilterBar from '../../components/BandSpace/Files/FileFilterBar.vue'
import FileList from '../../components/BandSpace/Files/FileList.vue'
import FileMoveDialog from '../../components/BandSpace/Files/FileMoveDialog.vue'
import FileShareDialog from '../../components/BandSpace/Files/FileShareDialog.vue'
import FileTrashList from '../../components/BandSpace/Files/FileTrashList.vue'
import FileUploadDialog from '../../components/BandSpace/Files/FileUploadDialog.vue'
import FileVersionPanel from '../../components/BandSpace/Files/FileVersionPanel.vue'
import FolderBreadcrumb from '../../components/BandSpace/Files/FolderBreadcrumb.vue'
import FolderEditDialog from '../../components/BandSpace/Files/FolderEditDialog.vue'
import FolderTree from '../../components/BandSpace/Files/FolderTree.vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { directChildren } from '../../composables/useFolderDragDrop.js'
import {
  isVirtualFolderId,
  listedFolderId,
  NO_FOLDER_LISTED,
  ROOT_FOLDER_ID,
  TRASH_FOLDER_ID
} from '../../constants/folderSelection.js'
import { useBandFilesStore } from '../../store/bandSpace/bandSpaceFiles.js'
import { listedFolderOfRows } from '../../utils/fileListing.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const filesStore = useBandFilesStore()
const { currentSpace } = useBandSpaceNavigation()
// Wipe any previous space's folders/files/tags synchronously before first
// render. The :key on <router-view> remounts this view on space switch but
// Pinia keeps A's data until cleared.
filesStore.clear()

const isAdmin = computed(() => currentSpace.value?.role === 'admin')

const uploadDialogVisible = ref(false)
const createFolderDialogVisible = ref(false)
const detailVisible = ref(false)
const shareDialogVisible = ref(false)
const shareDialogFileId = ref(null)
const versionPanelVisible = ref(false)
const versionPanelFileId = ref(null)
const versionPanelFileName = ref('')
const moveDialogVisible = ref(false)
const moveDialogFile = ref(null)
const autoStartRename = ref(false)

const bandSpaceId = computed(() => route.params.id)

/** null at the root, the folder id inside a folder, NO_FOLDER_LISTED anywhere that is not a place. */
const listedFolder = computed(() => listedFolderId(filesStore.activeFolderId))

/** A place in the tree, so there is a path to show and subfolders to descend into. */
const isInTree = computed(() => listedFolder.value !== NO_FOLDER_LISTED)

const showBreadcrumb = computed(() => isInTree.value)

const isTrashActive = computed(() => filesStore.activeFolderId === TRASH_FOLDER_ID)

const isAllFilesActive = computed(() => filesStore.activeFolderId === null)

/**
 * The place the rows on screen belong to, which is the selected one until a search widens the listing
 * past it. Kept apart from listedFolder: what the panel *is inside* still drives the breadcrumb and
 * what a new folder or an upload targets, even while the results come from elsewhere.
 */
const rowsFolder = computed(() => listedFolderOfRows(filesStore.activeFolderId, filesStore.filters))

/** A row can have come from any folder, so it has to say which one. */
const showFileLocation = computed(() => rowsFolder.value === NO_FOLDER_LISTED)

const isSpaceWideSearch = computed(() => filesStore.isSearching && isInTree.value)

/**
 * The folder the panel is actually inside, or null at the root. Neither the root nor the selections that
 * are not folders at all can reach the API as a parent_id, so a folder created from any of them belongs
 * at the root.
 */
const activeRealFolderId = computed(() => (isInTree.value ? listedFolder.value : null))

/**
 * The subfolders shown inline above the files. Derived from the tree already in the store, so descending
 * costs no request. Only a listing that is one place has any: a flat listing and a set of search results
 * hold files from every folder, so folder rows there would say nothing about where those files are.
 */
const inlineFolders = computed(() =>
  rowsFolder.value === NO_FOLDER_LISTED ? [] : directChildren(filesStore.folders, rowsFolder.value)
)

const isVirtualFolderActive = computed(() => isVirtualFolderId(filesStore.activeFolderId))

const emptyMessage = computed(() => {
  if (filesStore.activeFolderId === 'virtual:task') {
    return 'Aucun fichier attaché à une tâche pour le moment.'
  }
  if (filesStore.activeFolderId === 'virtual:finance') {
    return 'Aucun fichier attaché à une entrée financière pour le moment.'
  }
  if (filesStore.activeFolderId === 'virtual:note') {
    return 'Aucune image attachée à une note pour le moment.'
  }
  if (filesStore.activeFolderId === 'virtual:song') {
    return 'Aucun fichier attaché à une chanson pour le moment.'
  }
  if (filesStore.activeFolderId === 'virtual:setlist') {
    return 'Aucun fichier attaché à une setlist pour le moment.'
  }
  // A listing narrowed by the filter bar is empty because of what was asked for, not because the place
  // is empty, and telling the member to import a file is no answer to a search that found nothing.
  if (filesStore.isSearching) {
    return 'Aucun fichier ne correspond à cette recherche.'
  }
  if (filesStore.filters.tagId || filesStore.filters.mime) {
    return 'Aucun fichier ne correspond à ces filtres.'
  }
  if (listedFolder.value === null) {
    return 'Aucun fichier à la racine, commencez par en importer un.'
  }
  if (isInTree.value) {
    return 'Aucun fichier dans ce dossier — commencez par en importer un.'
  }
  return 'Aucun fichier — commencez par en importer un.'
})

let queryDebounce = null

onMounted(() => {
  loadAll()
})

onUnmounted(() => {
  if (queryDebounce) clearTimeout(queryDebounce)
  filesStore.clear()
})

function loadAll() {
  if (!bandSpaceId.value) return
  filesStore.fetchFolders(bandSpaceId.value)
  filesStore.fetchTags(bandSpaceId.value)
  filesStore.fetchQuota(bandSpaceId.value)
  filesStore.fetchArchivedCount(bandSpaceId.value)
  filesStore.fetchFiles(bandSpaceId.value)
}

function handleFolderSelect(folderId) {
  filesStore.setActiveFolder(folderId)
  filesStore.fetchFiles(bandSpaceId.value)
}

function handleLoadMore() {
  filesStore.fetchMoreFiles(bandSpaceId.value)
}

function handleFilterUpdate({ key, value }) {
  filesStore.setFilter(key, value)

  if (key === 'query') {
    if (queryDebounce) clearTimeout(queryDebounce)
    queryDebounce = setTimeout(() => filesStore.fetchFiles(bandSpaceId.value), 250)
    return
  }

  filesStore.fetchFiles(bandSpaceId.value)
}

function handleFileSelect(file) {
  router.push({ query: { ...route.query, file: file.id } })
}

function handleOpenShare(file) {
  if (!file) return
  shareDialogFileId.value = file.id
  shareDialogVisible.value = true
}

function handleOpenVersions(file) {
  if (!file) return
  versionPanelFileId.value = file.id
  versionPanelFileName.value = file.original_name
  versionPanelVisible.value = true
}

function handleOpenRename(file) {
  if (!file) return
  autoStartRename.value = true
  router.push({ query: { ...route.query, file: file.id } })
}

/**
 * Go to the folder a search result lives in. The search is dropped on the way, since it is what widened
 * the listing past any one folder: keeping it would answer the click with the same space-wide results.
 */
function handleOpenLocation(file) {
  const path = file.folder_path ?? []
  if (queryDebounce) clearTimeout(queryDebounce)
  filesStore.setFilter('query', '')
  handleFolderSelect(path.length > 0 ? path[path.length - 1].id : ROOT_FOLDER_ID)
}

function handleOpenMove(file) {
  if (!file) return
  moveDialogFile.value = file
  moveDialogVisible.value = true
}

function handleDrawerClose() {
  autoStartRename.value = false
  if (route.query.file) {
    router.replace({ query: { ...route.query, file: undefined } })
  }
}

function handleFileDeleted() {
  if (route.query.file) {
    router.replace({ query: { ...route.query, file: undefined } })
  }
}

watch(
  () => route.query.file,
  (fileId) => {
    if (fileId && bandSpaceId.value) {
      filesStore.setActiveFile(fileId)
      filesStore.fetchFileById(bandSpaceId.value, fileId)
      filesStore.fetchFileActivities(bandSpaceId.value, fileId)
      detailVisible.value = true
    } else {
      filesStore.setActiveFile(null)
      detailVisible.value = false
    }
  },
  { immediate: true }
)

function handleUploadSaved({ uploadedCount, total, label, quotaApproaching, interrupted }) {
  if (uploadedCount > 0) {
    toast.add({
      severity: 'success',
      summary: uploadedCount > 1 ? 'Fichiers téléversés' : 'Fichier téléversé',
      // One file keeps the sentence it has always been given: a bare count reads as a fragment when
      // there was never a batch to count. The tally is only worth quoting for a real batch.
      detail: total === 1 ? 'Le fichier a bien été ajouté.' : label,
      life: 3000
    })
  }
  if (quotaApproaching) {
    toast.add({
      severity: 'warn',
      summary: 'Quota presque atteint',
      detail: 'Vous avez atteint 80 % de votre quota de stockage.',
      life: 6000
    })
  }
  // A batch stopped in the middle may have left an upload the server took after the browser gave up
  // on it, so the only honest listing is the one read back from the server.
  if (interrupted) {
    filesStore.fetchFiles(bandSpaceId.value)
  }
  filesStore.fetchFolders(bandSpaceId.value)
}
</script>
