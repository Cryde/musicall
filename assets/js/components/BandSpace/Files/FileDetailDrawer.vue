<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    :style="{ width: '36rem' }"
    :show-close-icon="true"
    @hide="emit('close')"
  >
    <template #header>
      <div class="flex items-center gap-2 min-w-0 flex-1">
        <i :class="iconForMime(file?.mime_type)" class="text-xl text-surface-500"></i>
        <template v-if="!isRenaming">
          <span class="font-semibold truncate">{{ file?.original_name }}</span>
          <Button
            icon="pi pi-pencil"
            size="small"
            text
            rounded
            :disabled="!file"
            @click="startRename"
          />
        </template>
        <template v-else>
          <InputText
            v-model="renameValue"
            size="small"
            class="flex-1"
            :disabled="filesStore.isSavingFile"
            @keydown.enter.prevent="commitRename"
            @keydown.escape="cancelRename"
          />
          <Button
            icon="pi pi-check"
            size="small"
            severity="primary"
            :loading="filesStore.isSavingFile"
            @click="commitRename"
          />
          <Button
            icon="pi pi-times"
            size="small"
            text
            severity="secondary"
            :disabled="filesStore.isSavingFile"
            @click="cancelRename"
          />
        </template>
      </div>
    </template>

    <div v-if="filesStore.isLoadingActiveFile && !file" class="flex flex-col gap-3">
      <Skeleton width="100%" height="20rem" borderRadius="0.5rem" />
      <Skeleton width="60%" height="1rem" />
      <Skeleton width="80%" height="1rem" />
    </div>

    <div v-else-if="filesStore.activeFileError" class="flex flex-col items-center gap-4 py-12">
      <i class="pi pi-exclamation-circle text-4xl text-red-500"></i>
      <p class="text-sm text-surface-500">{{ filesStore.activeFileError }}</p>
    </div>

    <div v-else-if="file" class="flex flex-col gap-6">
      <div class="rounded-lg overflow-hidden border border-surface-200 dark:border-surface-700">
        <component
          :is="previewComponent.tag"
          v-bind="previewComponent.attrs"
          v-if="previewComponent"
          class="block w-full bg-surface-50 dark:bg-surface-950"
        />
        <div v-else class="flex flex-col items-center justify-center p-8 gap-3 bg-surface-50 dark:bg-surface-950">
          <i :class="iconForMime(file.mime_type)" class="text-5xl text-surface-400"></i>
          <p class="text-sm text-surface-500 italic">Aperçu indisponible</p>
          <Button
            label="Télécharger"
            icon="pi pi-download"
            size="small"
            severity="secondary"
            @click="openDownload"
          />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3 text-sm">
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">Téléversé par</p>
          <div class="flex items-center gap-2 mt-1">
            <Avatar
              v-if="file.created_by"
              :username="file.created_by.username"
              :picture-url="file.created_by.profile_picture_url"
              size="sm"
            />
            <span>{{ file.created_by?.username || '—' }}</span>
          </div>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">Taille</p>
          <p class="mt-1 tabular-nums">{{ formatSize(file.size) }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">Type</p>
          <p class="mt-1 truncate">{{ file.mime_type || '—' }}</p>
        </div>
        <div>
          <p class="text-xs text-surface-400 uppercase tracking-wide">Ajouté le</p>
          <p class="mt-1">{{ formatDate(file.creation_datetime) }}</p>
        </div>
        <div v-if="file.update_datetime">
          <p class="text-xs text-surface-400 uppercase tracking-wide">Modifié le</p>
          <p class="mt-1">{{ formatDate(file.update_datetime) }}</p>
        </div>
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs text-surface-400 uppercase tracking-wide">Étiquettes</label>
        <MultiSelect
          v-model="selectedTagIds"
          :options="filesStore.tags"
          option-label="name"
          option-value="id"
          placeholder="Aucune étiquette"
          :disabled="filesStore.isSavingFile"
          @hide="commitTagsIfChanged"
        />
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-xs text-surface-400 uppercase tracking-wide">Dossier</label>
        <Select
          :model-value="selectedFolderId"
          :options="folderOptions"
          option-label="label"
          option-value="value"
          placeholder="Racine"
          :show-clear="true"
          :disabled="filesStore.isSavingFile"
          @update:model-value="commitFolder"
        />
      </div>

      <FileActivityFeed :activities="filesStore.fileActivities" />
    </div>

    <template #footer>
      <div class="flex flex-wrap gap-2 justify-end">
        <Button
          label="Partager"
          icon="pi pi-share-alt"
          size="small"
          severity="secondary"
          :disabled="!file"
          @click="emit('share', file)"
        />
        <Button
          label="Versions"
          icon="pi pi-history"
          size="small"
          severity="secondary"
          :disabled="!file"
          @click="emit('versions', file)"
        />
        <Button
          label="Supprimer"
          icon="pi pi-trash"
          size="small"
          severity="danger"
          :disabled="!canDelete"
          :loading="filesStore.isDeletingFile"
          @click="confirmDelete"
        />
      </div>
    </template>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import MultiSelect from 'primevue/multiselect'
import Select from 'primevue/select'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { computed, ref, watch } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import Avatar from '../../User/Avatar.vue'
import FileActivityFeed from './FileActivityFeed.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true }
})

const emit = defineEmits(['close', 'share', 'versions', 'deleted'])

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()
const userSecurityStore = useUserSecurityStore()
const confirm = useConfirm()

const file = computed(() => filesStore.activeFile)

const isRenaming = ref(false)
const renameValue = ref('')
const initialTagIds = ref([])
const selectedTagIds = ref([])

const selectedFolderId = computed(() => file.value?.folder_id ?? null)

const canDelete = computed(() => {
  const f = file.value
  if (!f) return false
  const userId = userSecurityStore.userProfile?.id
  return f.created_by?.id === userId
})

const folderOptions = computed(() => {
  const out = [{ label: 'Racine', value: null }]
  const walk = (nodes, depth) => {
    for (const node of nodes) {
      out.push({ label: '— '.repeat(depth) + node.name, value: node.id })
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children, depth + 1)
      }
    }
  }
  walk(filesStore.folders, 0)
  return out
})

const previewComponent = computed(() => {
  const f = file.value
  if (!f?.mime_type) return null
  // Cache-bust on current_version_id so preview/audio/PDF refresh when a new
  // version is uploaded or a previous one is restored.
  const sep = f.download_url.includes('?') ? '&' : '?'
  const url = f.current_version_id
    ? `${f.download_url}${sep}v=${f.current_version_id}`
    : f.download_url
  if (f.mime_type === 'application/pdf') {
    return {
      tag: 'embed',
      attrs: { src: url, type: 'application/pdf', style: 'height: 60vh; width: 100%;' }
    }
  }
  if (f.mime_type.startsWith('image/')) {
    return {
      tag: 'img',
      attrs: {
        src: url,
        alt: f.original_name,
        style: 'max-height: 60vh; width: 100%; object-fit: contain;'
      }
    }
  }
  if (f.mime_type.startsWith('audio/')) {
    return {
      tag: 'audio',
      attrs: { src: url, controls: true, preload: 'metadata', style: 'width: 100%; padding: 1rem;' }
    }
  }
  return null
})

watch(
  file,
  (f) => {
    if (f) {
      initialTagIds.value = (f.tags || []).map((t) => t.id)
      selectedTagIds.value = [...initialTagIds.value]
      renameValue.value = f.original_name
    }
  },
  { immediate: true }
)

function startRename() {
  if (!file.value) return
  renameValue.value = file.value.original_name
  isRenaming.value = true
}

function cancelRename() {
  isRenaming.value = false
}

async function commitRename() {
  const trimmed = renameValue.value.trim()
  if (!trimmed || !file.value || trimmed === file.value.original_name) {
    isRenaming.value = false
    return
  }
  await filesStore.updateFile(props.bandSpaceId, file.value.id, { original_name: trimmed })
  isRenaming.value = false
  filesStore.fetchFileActivities(props.bandSpaceId, file.value.id)
}

async function commitTagsIfChanged() {
  if (!file.value) return
  const before = [...initialTagIds.value].sort().join(',')
  const after = [...selectedTagIds.value].sort().join(',')
  if (before === after) return
  await filesStore.updateFile(props.bandSpaceId, file.value.id, { tag_ids: selectedTagIds.value })
  initialTagIds.value = [...selectedTagIds.value]
  filesStore.fetchFileActivities(props.bandSpaceId, file.value.id)
}

async function commitFolder(value) {
  if (!file.value) return
  if (value === file.value.folder_id) return
  await filesStore.updateFile(props.bandSpaceId, file.value.id, { folder_id: value })
  filesStore.fetchFileActivities(props.bandSpaceId, file.value.id)
}

function confirmDelete() {
  if (!file.value) return
  const name = file.value.original_name
  const fileId = file.value.id
  confirm.require({
    message: `Supprimer définitivement « ${name} » ?`,
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Supprimer',
    rejectLabel: 'Annuler',
    acceptClass: 'p-button-danger',
    accept: async () => {
      await filesStore.deleteFile(props.bandSpaceId, fileId)
      emit('deleted', fileId)
      visible.value = false
    }
  })
}

function openDownload() {
  if (file.value?.download_url) {
    window.open(file.value.download_url, '_blank', 'noopener')
  }
}

function iconForMime(mime) {
  if (!mime) return 'pi pi-file'
  if (mime.startsWith('audio/')) return 'pi pi-volume-up'
  if (mime.startsWith('image/')) return 'pi pi-image'
  if (mime.startsWith('video/')) return 'pi pi-video'
  if (mime === 'application/pdf') return 'pi pi-file-pdf'
  return 'pi pi-file'
}

function formatSize(bytes) {
  if (bytes === null || bytes === undefined) return '—'
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  if (bytes < 1024 * 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`
  return `${(bytes / (1024 * 1024 * 1024)).toFixed(2)} Go`
}

function formatDate(iso) {
  if (!iso) return '—'
  const date = new Date(iso)
  return date.toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
