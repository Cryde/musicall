<template>
  <!-- Drawn over the empty list rather than in it, so the list under it stays a place to drop a song.
       Only the buttons take the pointer. -->
  <div class="flex flex-col items-center text-center gap-3 px-4 py-8 pointer-events-none">
    <span class="w-14 h-14 rounded-2xl flex items-center justify-center bg-surface-100 dark:bg-surface-800 text-primary" aria-hidden="true">
      <i class="pi pi-list text-2xl" />
    </span>
    <h3 class="m-0 text-lg font-semibold">{{ t('band_space_setlist.empty_state.title') }}</h3>
    <p class="m-0 max-w-md text-sm text-surface-600 dark:text-surface-300">
      {{ t('band_space_setlist.empty_state.intro') }}
    </p>
    <div v-if="!readonly" class="flex flex-wrap justify-center gap-2 pt-1 pointer-events-auto">
      <Button
        v-if="sources.length > 0"
        :label="t('band_space_setlist.empty_state.copy_from')"
        icon="pi pi-copy"
        severity="secondary"
        size="small"
        aria-haspopup="menu"
        aria-controls="setlist-copy-sources"
        :loading="busy === 'copy'"
        :disabled="busy !== null"
        @click="sourcesMenu?.toggle($event)"
      />
      <Button
        v-if="songCount > 0"
        :label="t('band_space_setlist.empty_state.add_all', { count: songCount })"
        severity="secondary"
        outlined
        size="small"
        :loading="busy === 'all'"
        :disabled="busy !== null"
        @click="emit('add-all')"
      />
    </div>
    <Menu id="setlist-copy-sources" ref="sourcesMenu" :model="sourceItems" :popup="true" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { programmeSummary } from '../../../../utils/setlistProgramme.js'

/**
 * A setlist with nothing in it yet (#1062): how to start, plus two shortcuts, copying another set's
 * running order or taking the whole repertoire.
 */
const props = defineProps({
  /** The band's other setlists that have something to copy. */
  sources: { type: Array, default: () => [] },
  songCount: { type: Number, default: 0 },
  /** 'copy' or 'all' while that shortcut runs. */
  busy: { type: String, default: null },
  readonly: { type: Boolean, default: false }
})

const emit = defineEmits(['copy-from', 'add-all'])
const { t } = useI18n()

const sourcesMenu = ref(null)

const sourceItems = computed(() =>
  props.sources.map((setlist) => {
    const { songs } = programmeSummary(setlist.items ?? [])
    return {
      label: setlist.archive_datetime
        ? t('band_space_setlist.empty_state.source_archived', { name: setlist.name }, songs)
        : t('band_space_setlist.empty_state.source', { name: setlist.name }, songs),
      command: () => emit('copy-from', setlist)
    }
  })
)
</script>
