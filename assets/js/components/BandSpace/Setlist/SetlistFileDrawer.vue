<template>
  <Drawer
    v-model:visible="visible"
    position="right"
    class="w-full! md:w-[400px]!"
    :header="t('band_space_setlist.file_drawer.header')"
  >
    <div class="flex flex-col gap-4">
      <p class="text-xs text-surface-400 italic">
        {{ t('band_space_setlist.file_drawer.intro') }}
      </p>
      <!-- Import only: detaching stays available on an archived setlist, the same way the API keeps
           it open, because it frees a file rather than adding to a list nobody can see. -->
      <AttachedFilesSection
        v-if="visible"
        :band-space-id="bandSpaceId"
        source-type="setlist"
        :source-id="setlistId"
        :source-label="setlistName"
        :can-attach="!readonly"
        can-detach
      />
    </div>
  </Drawer>
</template>

<script setup>
import Drawer from 'primevue/drawer'
import { useI18n } from 'vue-i18n'
import AttachedFilesSection from '../Files/AttachedFilesSection.vue'

defineProps({
  bandSpaceId: { type: String, required: true },
  setlistId: { type: String, required: true },
  setlistName: { type: String, default: '' },
  /** An archived setlist accepts no new attachment, so the import button goes away. */
  readonly: { type: Boolean, default: false }
})

const visible = defineModel('visible', { type: Boolean, default: false })

const { t } = useI18n()
</script>
