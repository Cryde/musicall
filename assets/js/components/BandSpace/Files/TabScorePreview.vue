<template>
  <!-- A glimpse of the tab, the way the band will read it: a click opens the reader. Gone when it fails. -->
  <button
    v-if="!failed"
    type="button"
    class="relative block h-40 w-full overflow-hidden bg-surface-0 text-left dark:bg-surface-900"
    :aria-label="t('band_space_files.attached_section.read_tab_named', { name: file.original_name })"
    @click="emit('read')"
  >
    <Skeleton v-if="!rendered" class="absolute inset-3 !h-auto" />
    <AlphaTabScore
      class="pointer-events-none h-full"
      :bytes="bytes"
      :dark="isDarkMode"
      :scale="0.75"
      preview
      @rendered="rendered = true"
      @unreadable="failed = true"
    />
    <span class="pointer-events-none absolute inset-x-0 bottom-0 h-10 bg-gradient-to-t from-surface-0 dark:from-surface-900" aria-hidden="true" />
  </button>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'
import { ref, shallowRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useDarkMode } from '../../../composables/useDarkMode.js'
import { withVersion } from '../../../utils/fileVersionUrl.js'
import AlphaTabScore from '../../Tabs/AlphaTabScore.vue'

/** The first bars of a song's tab on its page (#1431). alphaTab loads with it, no sound does. */

const props = defineProps({
  file: { type: Object, required: true }
})

const emit = defineEmits(['read'])

const { t } = useI18n()
const { isDarkMode } = useDarkMode()
const bytes = shallowRef(null)
const rendered = ref(false)
const failed = ref(false)

async function load() {
  rendered.value = false
  failed.value = false
  try {
    const response = await fetch(
      withVersion(props.file.download_url, props.file.current_version_id)
    )
    if (!response.ok) throw new Error(`HTTP ${response.status}`)
    bytes.value = await response.arrayBuffer()
  } catch {
    failed.value = true
  }
}

// Another file, or a new version of it, is the one the band plays from now on.
watch(() => withVersion(props.file.download_url, props.file.current_version_id), load, {
  immediate: true
})
</script>
