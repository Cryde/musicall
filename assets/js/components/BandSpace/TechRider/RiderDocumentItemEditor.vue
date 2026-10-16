<template>
  <div>
    <Message v-if="file?.is_archived" severity="warn" :closable="false" class="mb-3">
      {{ t('band_space_tech_rider.document.trashed_notice', { name: file.original_name }) }}
    </Message>

    <div v-if="!file || file.is_archived" class="text-center py-6">
      <i class="pi pi-file text-3xl text-surface-400 dark:text-surface-500" aria-hidden="true" />
      <p class="mt-2 text-surface-600 dark:text-surface-300">
        {{
          file
            ? t('band_space_tech_rider.document.trashed_hint')
            : t('band_space_tech_rider.document.no_file')
        }}
      </p>
      <Button
        v-if="!readOnly"
        :label="
          file
            ? t('band_space_tech_rider.document.choose_other')
            : t('band_space_tech_rider.document.choose')
        "
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
          :alt="t('band_space_tech_rider.document.preview_alt', { name: file.original_name })"
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
              {{ t('band_space_tech_rider.document.open_pdf') }}
            </a>
          </div>
        </div>
      </div>

      <div v-if="!readOnly" class="flex items-center gap-2">
        <Button
          :label="t('band_space_tech_rider.document.change_file')"
          icon="pi pi-folder-open"
          severity="secondary"
          text
          size="small"
          @click="pickerOpen = true"
        />
        <Button
          :label="t('band_space_tech_rider.common.remove')"
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
import { useI18n } from 'vue-i18n'
import RiderFilePickerDialog from './RiderFilePickerDialog.vue'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  itemId: { type: String, required: true },
  file: { type: Object, default: null },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['choose'])

const { t } = useI18n()

const pickerOpen = ref(false)

const isImage = computed(() => (props.file?.mime_type ?? '').startsWith('image/'))
</script>
