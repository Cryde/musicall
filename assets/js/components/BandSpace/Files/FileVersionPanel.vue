<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="dialogHeader"
    :style="{ width: '40rem' }"
    :closable="!filesStore.isUploadingVersion && !filesStore.isRollingBack"
  >
    <div class="flex flex-col gap-4">
      <div class="flex items-center justify-between">
        <p class="text-sm text-surface-500">{{ versionsCountLabel }}</p>
        <Button
          label="Téléverser une nouvelle version"
          icon="pi pi-cloud-upload"
          size="small"
          :disabled="filesStore.isUploadingVersion || filesStore.isRollingBack"
          @click="newVersionDialogVisible = true"
        />
      </div>

      <div v-if="filesStore.isLoadingVersions && filesStore.versions.length === 0" class="flex flex-col gap-2">
        <Skeleton v-for="i in 3" :key="i" width="100%" height="3.5rem" borderRadius="0.5rem" />
      </div>

      <p
        v-else-if="filesStore.versions.length === 0"
        class="text-sm italic text-surface-400 text-center py-8"
      >
        Aucune version pour ce fichier.
      </p>

      <div v-else class="flex flex-col gap-2">
        <div
          v-for="version in sortedVersions"
          :key="version.id"
          class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-700"
        >
          <div class="flex flex-col items-center w-10">
            <span class="font-mono text-xs text-surface-500">v{{ version.version_number }}</span>
            <Tag
              v-if="version.is_current"
              value="Actuelle"
              severity="success"
              class="text-[10px] mt-1"
            />
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-xs text-surface-500">
              <Avatar
                v-if="version.created_by"
                :username="version.created_by.username"
                :picture-url="version.created_by.profile_picture_url"
                size="sm"
              />
              <span class="font-medium text-surface-700 dark:text-surface-200">
                {{ version.created_by?.username || '—' }}
              </span>
              <span>·</span>
              <span class="tabular-nums">{{ formatSize(version.size) }}</span>
              <span>·</span>
              <span>{{ formatDate(version.creation_datetime) }}</span>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <Button
              icon="pi pi-download"
              size="small"
              text
              severity="secondary"
              aria-label="Télécharger cette version"
              v-tooltip.top="'Télécharger cette version'"
              @click="downloadVersion(version)"
            />
            <Button
              v-if="!version.is_current"
              icon="pi pi-replay"
              size="small"
              text
              severity="secondary"
              aria-label="Restaurer cette version"
              v-tooltip.top="'Restaurer cette version'"
              :disabled="filesStore.isRollingBack"
              @click="confirmRollback(version)"
            />
          </div>
        </div>
      </div>
    </div>

    <FileNewVersionDialog
      v-model:visible="newVersionDialogVisible"
      :band-space-id="bandSpaceId"
      :file-id="fileId"
      @uploaded="handleVersionUploaded"
    />
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, ref, watch } from 'vue'
import { useBandFilesStore } from '../../../store/bandSpace/bandSpaceFiles.js'
import Avatar from '../../User/Avatar.vue'
import FileNewVersionDialog from './FileNewVersionDialog.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  fileId: { type: String, default: null },
  fileName: { type: String, default: '' }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const filesStore = useBandFilesStore()
const confirm = useConfirm()
const toast = useToast()

const newVersionDialogVisible = ref(false)

const dialogHeader = computed(() => {
  if (!props.fileName) return 'Versions du fichier'
  return `Versions de ${props.fileName}`
})

const sortedVersions = computed(() =>
  [...filesStore.versions].sort((a, b) => b.version_number - a.version_number)
)

const versionsCountLabel = computed(() => {
  const n = filesStore.versions.length
  if (n === 0) return ''
  return n === 1 ? '1 version' : `${n} versions`
})

watch(
  [visible, () => props.fileId],
  ([open, fileId]) => {
    if (open && fileId) {
      filesStore.fetchVersions(props.bandSpaceId, fileId)
    }
  },
  { immediate: true }
)

function downloadVersion(version) {
  if (version.download_url) {
    window.open(version.download_url, '_blank', 'noopener')
  }
}

function confirmRollback(version) {
  confirm.require({
    message: `Restaurer la version v${version.version_number} comme version actuelle ?`,
    header: 'Confirmer la restauration',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Restaurer',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await filesStore.rollbackVersion(props.bandSpaceId, props.fileId, version.version_number)
        toast.add({
          severity: 'success',
          summary: 'Version restaurée',
          detail: `La version v${version.version_number} est maintenant la version actuelle.`,
          life: 3500
        })
      } catch (e) {
        toast.add({
          severity: 'error',
          summary: 'Restauration impossible',
          detail: e.message,
          life: 5000
        })
      }
    }
  })
}

function handleVersionUploaded({ quotaApproaching }) {
  toast.add({
    severity: 'success',
    summary: 'Nouvelle version téléversée',
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
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}
</script>
