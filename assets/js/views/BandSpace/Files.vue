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
      </aside>

      <section class="flex-1 flex flex-col gap-4 min-w-0">
        <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700">
          <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[200px]">
              <FileFilterBar
                :filters="filesStore.filters"
                :tags="filesStore.tags"
                @update-filter="handleFilterUpdate"
              />
            </div>
            <Button
              label="Téléverser"
              icon="pi pi-cloud-upload"
              size="small"
              @click="uploadDialogVisible = true"
            />
          </div>
        </div>

        <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700">
          <FileList
            :files="filesStore.files"
            :is-loading="filesStore.isLoadingFiles"
            :empty-message="emptyMessage"
            @select="handleFileSelect"
          />
        </div>
      </section>
    </div>

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
import FileShareDialog from '../../components/BandSpace/Files/FileShareDialog.vue'
import FileUploadDialog from '../../components/BandSpace/Files/FileUploadDialog.vue'
import FileVersionPanel from '../../components/BandSpace/Files/FileVersionPanel.vue'
import FolderTree from '../../components/BandSpace/Files/FolderTree.vue'
import { useBandSpaceNavigation } from '../../composables/useBandSpaceNavigation.js'
import { useBandFilesStore } from '../../store/bandSpace/bandSpaceFiles.js'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const filesStore = useBandFilesStore()
const { currentSpace } = useBandSpaceNavigation()

const isAdmin = computed(() => currentSpace.value?.role === 'admin')

const uploadDialogVisible = ref(false)
const detailVisible = ref(false)
const shareDialogVisible = ref(false)
const shareDialogFileId = ref(null)
const versionPanelVisible = ref(false)
const versionPanelFileId = ref(null)
const versionPanelFileName = ref('')

const bandSpaceId = computed(() => route.params.id)

const emptyMessage = computed(() => {
  if (filesStore.activeFolderId === 'virtual:task') {
    return 'Aucun fichier attaché à une tâche pour le moment.'
  }
  if (filesStore.activeFolderId === 'virtual:finance') {
    return 'Aucun fichier attaché à une entrée financière pour le moment.'
  }
  if (filesStore.activeFolderId !== null) {
    return 'Aucun fichier dans ce dossier — commencez par en téléverser un.'
  }
  return 'Aucun fichier — commencez par en téléverser un.'
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
  filesStore.fetchFiles(bandSpaceId.value)
}

function handleFolderSelect(folderId) {
  filesStore.setActiveFolder(folderId)
  filesStore.fetchFiles(bandSpaceId.value)
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

watch(bandSpaceId, () => {
  filesStore.clear()
  loadAll()
})

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

function handleDrawerClose() {
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

function handleUploadSaved({ quotaApproaching }) {
  toast.add({
    severity: 'success',
    summary: 'Fichier téléversé',
    detail: 'Le fichier a bien été ajouté.',
    life: 3000
  })
  if (quotaApproaching) {
    toast.add({
      severity: 'warn',
      summary: 'Quota presque atteint',
      detail: 'Vous avez atteint 80 % de votre quota de stockage.',
      life: 6000
    })
  }
  filesStore.fetchFolders(bandSpaceId.value)
}
</script>
