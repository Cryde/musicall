<template>
  <div class="flex flex-col gap-3">
    <div class="flex items-center justify-between">
      <h4 class="text-sm font-semibold text-surface-700 dark:text-surface-200">
        Pièces jointes
        <span v-if="attachedFiles.length > 0" class="text-xs text-surface-400 ml-1">
          ({{ attachedFiles.length }})
        </span>
      </h4>
      <Button
        v-if="canAttach"
        label="Joindre un fichier"
        icon="pi pi-paperclip"
        size="small"
        severity="secondary"
        @click="dialogVisible = true"
      />
    </div>

    <div v-if="isLoading && attachedFiles.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 2" :key="i" width="100%" height="2.5rem" borderRadius="0.5rem" />
    </div>

    <p v-else-if="attachedFiles.length === 0" class="text-xs italic text-surface-400">
      Aucun fichier attaché.
    </p>

    <div
      v-for="file in attachedFiles"
      :key="file.id"
      class="flex items-center gap-2 p-2 rounded-lg border border-surface-200 dark:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
    >
      <button
        type="button"
        class="flex items-center gap-2 flex-1 min-w-0 text-left"
        @click="openFileDrawer(file)"
      >
        <i :class="iconForMime(file.mime_type)" class="text-surface-500"></i>
        <span class="text-sm font-medium truncate">{{ file.original_name }}</span>
        <span class="text-xs text-surface-400 tabular-nums">{{ formatBytes(file.size) }}</span>
      </button>
      <Avatar
        v-if="file.created_by"
        :username="file.created_by.username"
        :picture-url="file.created_by.profile_picture_url"
        size="sm"
      />
      <Button
        v-if="canAttach"
        icon="pi pi-times"
        size="small"
        text
        severity="secondary"
        aria-label="Détacher"
        v-tooltip.top="'Détacher'"
        @click="confirmDetach(file)"
      />
    </div>

    <AttachFileDialog
      v-if="canAttach && dialogVisible"
      v-model:visible="dialogVisible"
      :band-space-id="bandSpaceId"
      :source-type="sourceType"
      :source-id="sourceId"
      @attached="handleAttached"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import bandSpaceFilesApi from '../../../api/bandSpace/band-space-files.js'
import { formatBytes } from '../../../utils/formatBytes.js'
import Avatar from '../../User/Avatar.vue'
import AttachFileDialog from './AttachFileDialog.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  sourceType: { type: String, required: true, validator: (v) => ['task', 'finance'].includes(v) },
  sourceId: { type: String, required: true },
  canAttach: { type: Boolean, default: true }
})

const emit = defineEmits(['attached', 'detached'])

const router = useRouter()
const confirm = useConfirm()
const toast = useToast()

const attachedFiles = ref([])
const isLoading = ref(false)
const dialogVisible = ref(false)

watch(
  () => [props.bandSpaceId, props.sourceType, props.sourceId],
  () => {
    loadFiles()
  },
  { immediate: true }
)

async function loadFiles() {
  if (!props.sourceId) return
  isLoading.value = true
  try {
    attachedFiles.value = await bandSpaceFilesApi.getAttachedFiles(
      props.bandSpaceId,
      props.sourceType,
      props.sourceId
    )
  } catch {
    attachedFiles.value = []
  } finally {
    isLoading.value = false
  }
}

function openFileDrawer(file) {
  router.push({
    name: 'app_band_files',
    params: { id: props.bandSpaceId },
    query: { file: file.id }
  })
}

function confirmDetach(file) {
  confirm.require({
    message: `Détacher « ${file.original_name} » de cette ${props.sourceType === 'task' ? 'tâche' : 'entrée'} ?`,
    header: 'Confirmer le détachement',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Détacher',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await bandSpaceFilesApi.detachFromSource(
          props.bandSpaceId,
          props.sourceType,
          props.sourceId,
          file.id
        )
        attachedFiles.value = attachedFiles.value.filter((f) => f.id !== file.id)
        emit('detached', file.id)
        toast.add({
          severity: 'success',
          summary: 'Fichier détaché',
          life: 3000
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Détachement impossible',
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}

function handleAttached(file) {
  loadFiles()
  emit('attached', file?.id ?? null)
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
