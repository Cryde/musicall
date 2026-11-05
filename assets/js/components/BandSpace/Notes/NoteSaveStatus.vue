<template>
  <!-- Always there, so a screen reader hears each change of status. -->
  <span role="status" class="text-xs text-surface-500 whitespace-nowrap">
    <template v-if="status === 'saving'">
      <i class="pi pi-spin pi-spinner mr-1"></i>{{ t('band_space_notes.editor.save_status.saving') }}
    </template>
    <template v-else-if="status === 'saved'">
      <i class="pi pi-check mr-1 text-green-500"></i>{{ t('band_space_notes.editor.save_status.saved') }}
    </template>
    <template v-else-if="status === 'conflict'">
      <i class="pi pi-exclamation-triangle mr-1 text-orange-500"></i>{{ t('band_space_notes.editor.save_status.conflict') }}
    </template>
    <template v-else-if="status === 'error'">
      <i class="pi pi-times mr-1 text-red-500"></i>{{ t('band_space_notes.editor.save_status.error') }}
    </template>
  </span>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

/** Where the open note's autosave stands: in the editor's head on desktop, in the phone's bar. */
defineProps({
  status: { type: String, default: null }
})

const { t } = useI18n()
</script>
