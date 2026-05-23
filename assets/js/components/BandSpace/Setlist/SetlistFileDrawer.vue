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
import { useToast } from 'primevue/usetoast'
import { ref } from 'vue'
import bandSpaceSetlistsApi from '../../../api/bandSpace/band-space-setlists.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const toast = useToast()
const fileInput = ref(null)
const isUploading = ref(false)

async function handleFileSelected(event) {
  const file = event.target.files?.[0]
  if (!file) return
  isUploading.value = true
  try {
    await bandSpaceSetlistsApi.uploadFile(props.bandSpaceId, props.setlistId, file)
    toast.add({ severity: 'success', summary: 'Fichier téléversé', life: 3000 })
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Erreur', detail: e.message, life: 5000 })
  } finally {
    isUploading.value = false
    if (fileInput.value) fileInput.value.value = ''
  }
}
</script>
