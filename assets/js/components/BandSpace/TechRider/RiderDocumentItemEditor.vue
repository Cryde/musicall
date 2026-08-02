<template>
  <div>
    <Message v-if="file?.is_archived" severity="warn" :closable="false" class="mb-3">
      Le fichier « {{ file.original_name }} » est dans la corbeille. Restaurez-le depuis les
      fichiers pour que cette page réapparaisse dans le document.
    </Message>

    <div v-if="!file || file.is_archived" class="text-center py-6">
      <i class="pi pi-file text-3xl text-surface-400 dark:text-surface-500" aria-hidden="true" />
      <p class="mt-2 text-surface-600 dark:text-surface-300">
        {{
          file
            ? 'La page réapparaîtra si le fichier est restauré, ou choisissez-en un autre.'
            : 'Aucun fichier choisi. Une page peut être une image ou un PDF déjà présent dans vos fichiers.'
        }}
      </p>
      <Button
        v-if="!readOnly"
        :label="file ? 'Choisir un autre fichier' : 'Choisir un fichier'"
        icon="pi pi-folder-open"
        severity="secondary"
        outlined
        class="mt-3"
        @click="pickerOpen = true"
      />
    </div>

    <div v-else class="flex flex-col gap-3">
      <div
        class="rounded-lg border border-surface-200 dark:border-surface-700 overflow-hidden bg-surface-50 dark:bg-surface-800"
      >
        <img
          v-if="isImage"
          :src="file.download_url"
          :alt="`Aperçu de ${file.original_name}`"
          class="max-h-96 w-full object-contain"
        />
        <!-- No inline PDF render: a preview iframe per item would load every PDF of the rider
             at once. The name and a link are enough to confirm the right page is attached. -->
        <div v-else class="flex items-center gap-3 p-4">
          <i class="pi pi-file-pdf text-2xl text-red-600 dark:text-red-400" aria-hidden="true" />
          <div class="min-w-0">
            <p class="font-medium truncate">{{ file.original_name }}</p>
            <a
              :href="file.download_url"
              target="_blank"
              rel="noopener"
              class="text-sm text-primary hover:underline"
            >
              Ouvrir le PDF
            </a>
          </div>
        </div>
      </div>

      <div v-if="!readOnly" class="flex items-center gap-2">
        <Button
          label="Changer de fichier"
          icon="pi pi-folder-open"
          severity="secondary"
          text
          size="small"
          @click="pickerOpen = true"
        />
        <Button
          label="Retirer"
          icon="pi pi-times"
          severity="secondary"
          text
          size="small"
          @click="emit('choose', { itemId, fileId: null })"
        />
      </div>
    </div>

    <RiderFilePickerDialog
      v-model:visible="pickerOpen"
      :band-space-id="bandSpaceId"
      @choose="(fileId) => emit('choose', { itemId, fileId })"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Message from 'primevue/message'
import { computed, ref } from 'vue'
import RiderFilePickerDialog from './RiderFilePickerDialog.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  itemId: { type: String, required: true },
  file: { type: Object, default: null },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['choose'])

const pickerOpen = ref(false)

const isImage = computed(() => (props.file?.mime_type ?? '').startsWith('image/'))
</script>
