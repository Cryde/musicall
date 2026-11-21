<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="t('band_space_tabs.same_name.title')"
    v-bind="sheetDialog"
    @after-hide="afterHide.onAfterHide"
  >
    <p class="m-0 text-sm text-surface-700 dark:text-surface-200">
      {{ t('band_space_tabs.same_name.message', { name, folder }) }}
    </p>

    <template #footer>
      <Button :label="t('band_space_files.common.cancel')" severity="secondary" text @click="visible = false" />
      <Button
        :label="t('band_space_tabs.same_name.separate')"
        severity="secondary"
        outlined
        @click="afterHide.run(() => emit('separate'))"
      />
      <Button
        :label="t('band_space_tabs.same_name.new_version')"
        icon="pi pi-history"
        @click="afterHide.run(() => emit('new-version'))"
      />
    </template>
  </Dialog>
</template>

<script setup>
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import { useI18n } from 'vue-i18n'
import { useActionAfterSheetHide } from '../../../composables/useActionAfterSheetHide.js'
import { useSheetDialog } from '../../../composables/useSheetDialog.js'

/**
 * A tab picked under a name the space already holds (#1430): a new version keeps the history in one
 * file, which is what a band re-exporting its song wants.
 */
defineProps({
  name: { type: String, required: true },
  /** Where the existing tab lives, as Files names it. */
  folder: { type: String, required: true }
})

const emit = defineEmits(['new-version', 'separate'])

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
const sheetDialog = useSheetDialog('36rem')
// The next dialog opens once this one is gone, or the page loses its scroll lock.
const afterHide = useActionAfterSheetHide(visible)
</script>
