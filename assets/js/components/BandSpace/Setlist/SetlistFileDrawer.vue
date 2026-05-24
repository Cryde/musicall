<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    :style="{ width: '400px' }"
    header="Fichiers du setlist"
  >
    <div class="flex flex-col gap-4">
      <p class="text-xs text-surface-400 italic">
        Téléversez les fichiers liés à ce setlist (timing sheet, notes scéniques, etc.).
        Ils apparaitront dans le dossier virtuel «&nbsp;Setlists&nbsp;» du module Fichiers.
      </p>

      <div v-if="isLoadingFiles && files.length === 0" class="flex flex-col gap-2">
        <Skeleton v-for="i in 2" :key="i" height="2.25rem" borderRadius="0.5rem" />
      </div>

      <ul v-else-if="files.length > 0" class="list-none p-0 m-0 flex flex-col gap-1">
        <li
          v-for="file in files"
          :key="file.id"
          class="flex items-center gap-2 px-2 py-1.5 rounded-lg bg-surface-50 dark:bg-surface-800 text-sm"
        >
          <i class="pi pi-file text-surface-500 shrink-0" aria-hidden="true"></i>
          <div class="flex-1 min-w-0">
            <div class="truncate">{{ file.original_name }}</div>
            <div v-if="file.size" class="text-xs text-surface-500 tabular-nums">
              {{ formatBytes(file.size) }}
            </div>
          </div>
          <Button
            icon="pi pi-times"
            severity="secondary"
            text
            rounded
            size="small"
            aria-label="Détacher"
            v-tooltip.left="'Détacher ce fichier'"
            @click="confirmDetach(file)"
          />
        </li>
      </ul>

      <p v-else class="text-xs text-surface-400 italic">Aucun fichier pour le moment.</p>

      <input ref="fileInput" type="file" class="hidden" @change="handleFileSelected" />
      <Button
        label="Téléverser un fichier"
        icon="pi pi-cloud-upload"
        severity="secondary"
        :loading="isUploading"
        @click="fileInput?.click()"
      />
    </div>
  </Drawer>
</template>

<script setup>
import Button from 'primevue/button'
import Drawer from 'primevue/drawer'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { ref, watch } from 'vue'
import bandSpaceSetlistsApi from '../../../api/bandSpace/band-space-setlists.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const toast = useToast()
const confirm = useConfirm()
const fileInput = ref(null)
const isUploading = ref(false)
const files = ref([])
const isLoadingFiles = ref(false)

function formatBytes(bytes) {
  if (!bytes) return ''
  const units = ['o', 'Ko', 'Mo', 'Go']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
}

async function loadFiles() {
  if (!props.setlistId) return
  isLoadingFiles.value = true
  try {
    files.value = await bandSpaceSetlistsApi.getAttachedFiles(props.bandSpaceId, props.setlistId)
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isLoadingFiles.value = false
  }
}

watch(
  visible,
  (isOpen) => {
    if (isOpen) {
      loadFiles()
    } else {
      files.value = []
    }
  },
  { immediate: true }
)

async function handleFileSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return
  isUploading.value = true
  try {
    await bandSpaceSetlistsApi.uploadFile(props.bandSpaceId, props.setlistId, file)
    toast.add({ severity: 'success', summary: 'Fichier téléversé', life: 3000 })
    await loadFiles()
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isUploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}

function confirmDetach(file) {
  confirm.require({
    message: `Détacher le fichier « ${file.original_name} » de ce setlist ?`,
    header: 'Confirmer',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Détacher',
    rejectLabel: 'Annuler',
    accept: async () => {
      try {
        await bandSpaceSetlistsApi.detachFile(props.bandSpaceId, props.setlistId, file.id)
        toast.add({ severity: 'success', summary: 'Fichier détaché', life: 3000 })
        await loadFiles()
      } catch (e) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
      }
    }
  })
}
</script>
