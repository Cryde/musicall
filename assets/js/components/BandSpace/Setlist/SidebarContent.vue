<template>
  <div>
    <button
      type="button"
      class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
      :class="navButtonClasses('repertoire')"
      :aria-current="activeView === 'repertoire' ? 'true' : null"
      @click="emit('select-repertoire')"
    >
      <i class="pi pi-book text-emerald-600"></i>
      <span class="flex-1 truncate font-medium">{{ t('band_space_setlist.common.repertoire') }}</span>
      <Badge :value="songsCount" severity="secondary" />
    </button>
    <button
      type="button"
      class="w-full text-left flex items-center gap-2 px-3 py-2 mt-1 rounded-lg transition-colors"
      :class="navButtonClasses('lyrics')"
      :aria-current="activeView === 'lyrics' ? 'true' : null"
      @click="emit('select-lyrics')"
    >
      <i class="pi pi-align-left text-sky-600" aria-hidden="true"></i>
      <span class="flex-1 truncate font-medium">{{ t('band_space_setlist.sidebar.lyrics') }}</span>
      <Badge :value="lyricsCount" severity="secondary" />
    </button>

    <h2 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400 px-3 mt-5 mb-2">
      {{ t('band_space_setlist.sidebar.setlists') }}
    </h2>
    <div v-if="isLoadingSetlists && setlists.length === 0" class="flex flex-col gap-1 px-3">
      <Skeleton v-for="i in 3" :key="i" width="100%" height="1.75rem" borderRadius="0.375rem" />
    </div>
    <div v-else-if="setlists.length === 0" class="text-xs text-surface-400 italic px-3 py-2">
      {{ t('band_space_setlist.sidebar.empty') }}
    </div>
    <ul v-else class="flex flex-col gap-1">
      <li v-for="setlist in setlists" :key="setlist.id">
        <button
          type="button"
          class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
          :class="setlistButtonClasses(setlist.id)"
          @click="emit('select-setlist', setlist.id)"
        >
          <i class="pi pi-list text-rose-600" aria-hidden="true"></i>
          <span class="flex-1 min-w-0 flex flex-col">
            <span class="truncate">{{ setlist.name }}</span>
            <span class="text-xs text-surface-600 dark:text-surface-300 tabular-nums">{{ setlistLine(setlist) }}</span>
          </span>
        </button>
      </li>
    </ul>

    <div class="mt-5">
      <Button
        :label="t('band_space_setlist.sidebar.new_setlist')"
        icon="pi pi-plus"
        size="small"
        severity="secondary"
        class="w-full"
        @click="emit('new-setlist')"
      />
    </div>

    <!-- Same Corbeille entry as the Files sidebar, so a member who has met one trash recognises the
         other. It holds archived setlists and archived titles together: they are one module. -->
    <div class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700">
      <button
        type="button"
        class="w-full flex items-center gap-2 px-3 py-2 rounded-md text-sm text-left transition-colors duration-150"
        :class="trashButtonClasses"
        :aria-current="activeView === 'trash' ? 'true' : null"
        @click="emit('select-trash')"
      >
        <i class="pi pi-trash" aria-hidden="true"></i>
        <span class="flex-1 truncate">{{ t('band_space_setlist.sidebar.trash') }}</span>
        <span class="text-xs text-surface-500 tabular-nums">{{ trashCount }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDuration } from '../../../utils/setlistDuration.js'
import { programmeSummary } from '../../../utils/setlistProgramme.js'

const props = defineProps({
  songsCount: { type: Number, required: true },
  lyricsCount: { type: Number, default: 0 },
  setlists: { type: Array, required: true },
  isLoadingSetlists: { type: Boolean, default: false },
  activeView: { type: String, required: true }, // 'repertoire' | 'lyrics' | 'setlist' | 'trash'
  activeSetlistId: { type: String, default: null },
  trashCount: { type: Number, default: 0 }
})

const emit = defineEmits([
  'select-repertoire',
  'select-lyrics',
  'select-setlist',
  'select-trash',
  'new-setlist'
])
const { t } = useI18n()

function navButtonClasses(view) {
  return props.activeView === view
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
}

const trashButtonClasses = computed(() =>
  props.activeView === 'trash'
    ? 'bg-primary-100 dark:bg-primary-900/40 text-primary-700 dark:text-primary-200 font-medium'
    : 'text-surface-700 dark:text-surface-200 hover:bg-surface-100 dark:hover:bg-surface-800'
)

function setlistButtonClasses(id) {
  return props.activeView === 'setlist' && props.activeSetlistId === id
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
}

/** « 7 titres · 27:17 », or « Vide »: enough to pick the right set without opening it. */
function setlistLine(setlist) {
  const { songs, total } = programmeSummary(setlist.items ?? [])
  if (songs === 0) return t('band_space_setlist.sidebar.setlist_empty')
  return total > 0
    ? t(
        'band_space_setlist.sidebar.song_count_with_duration',
        { duration: formatDuration(total) },
        songs
      )
    : t('band_space_setlist.sidebar.song_count', songs)
}
</script>
