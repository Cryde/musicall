<template>
  <div class="flex flex-col gap-4">
    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700 flex flex-wrap items-center gap-2">
      <div class="flex-1 min-w-[200px]">
        <IconField iconPosition="left">
          <InputIcon class="pi pi-search" />
          <InputText v-model="query" type="search" :placeholder="t('band_space_setlist.lyrics_view.search_placeholder')" :aria-label="t('band_space_setlist.lyrics_view.search_aria')" class="w-full" />
        </IconField>
      </div>
      <Button
        v-for="option in filterOptions"
        :key="option.value"
        :label="option.label"
        size="small"
        severity="secondary"
        :outlined="filter !== option.value"
        :aria-pressed="filter === option.value"
        @click="filter = option.value"
      />
      <Button :label="t('band_space_setlist.lyrics_view.add')" icon="pi pi-plus" size="small" @click="newLyricsOpen = true" />
    </div>

    <div class="bg-surface-0 dark:bg-surface-900 rounded-2xl border border-surface-200 dark:border-surface-700 overflow-hidden">
      <div v-if="lyricsStore.isLoading && lyricsStore.lyrics.length === 0" class="p-4 flex flex-col gap-2">
        <Skeleton v-for="i in 4" :key="i" width="100%" height="3rem" borderRadius="0.5rem" />
      </div>
      <div v-else-if="visibleLyrics.length === 0" class="p-8 text-center text-surface-600 dark:text-surface-300">
        <i class="pi pi-align-left text-3xl mb-3 block" aria-hidden="true"></i>
        {{ lyricsStore.lyrics.length > 0 ? t('band_space_setlist.lyrics_view.no_match') : t('band_space_setlist.lyrics_view.empty') }}
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-surface-50 dark:bg-surface-800 text-xs uppercase tracking-wide text-surface-600 dark:text-surface-300">
          <tr>
            <th class="text-left px-4 py-3">{{ t('band_space_setlist.lyrics_view.column_title') }}</th>
            <th class="text-left px-3 py-3">{{ t('band_space_setlist.lyrics_view.column_song') }}</th>
            <th class="text-left px-3 py-3 hidden md:table-cell">{{ t('band_space_setlist.lyrics_view.column_updated') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="item in visibleLyrics"
            :key="item.id"
            class="border-t border-surface-100 dark:border-surface-800 hover:bg-surface-50 dark:hover:bg-surface-800"
          >
            <td class="px-4 py-2.5 font-medium">
              <span class="flex items-center gap-2">
                <i
                  role="img"
                  :class="['pi pi-align-left text-sm', item.has_content ? 'text-primary' : 'text-surface-400 dark:text-surface-500']"
                  v-tooltip.top="contentLabel(item)"
                  :aria-label="contentLabel(item)"
                />
                <button type="button" class="text-left hover:underline" @click="openDrawer(item)">{{ item.title }}</button>
              </span>
            </td>
            <td class="px-3 py-2.5">
              <span v-if="item.song" class="flex flex-wrap items-center gap-2">
                <span>{{ item.song.title }}</span>
                <Tag v-if="item.song.archive_datetime" :value="t('band_space_setlist.lyrics_drawer.song_archived')" severity="secondary" />
              </span>
              <span v-else class="text-xs italic text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.lyrics_view.unlinked') }}</span>
            </td>
            <td class="px-3 py-2.5 hidden md:table-cell text-surface-600 dark:text-surface-300 tabular-nums">
              {{ formatDateCompactWithYear(item.update_datetime ?? item.creation_datetime) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <NewLyricsDialog v-model:visible="newLyricsOpen" :band-space-id="bandSpaceId" @created="openDrawer($event, { edit: true })" />

    <LyricsDetailDrawer
      v-model:visible="drawerVisible"
      :band-space-id="bandSpaceId"
      :lyrics="drawerLyrics"
      :edit-on-open="drawerEdits"
      @archived="drawerVisible = false"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Skeleton from 'primevue/skeleton'
import Tag from 'primevue/tag'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useBandLyricsStore } from '../../../../store/bandSpace/bandSpaceLyrics.js'
import { formatDateCompactWithYear } from '../../../../utils/date.js'
import { filterLyrics, lyricsCounts } from '../../../../utils/lyricsList.js'
import LyricsDetailDrawer from './LyricsDetailDrawer.vue'
import NewLyricsDialog from './NewLyricsDialog.vue'

/** The band's lyrics on their own (#1261): linked to a song or still waiting for one. */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  /** Lyrics a link pointed at, opened in the drawer once the list is loaded. */
  focusLyricsId: { type: String, default: null }
})

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const lyricsStore = useBandLyricsStore()

const query = ref('')
const filter = ref('all')
const newLyricsOpen = ref(false)
const drawerVisible = ref(false)
const drawerLyrics = ref(null)
const drawerEdits = ref(false)

const counts = computed(() => lyricsCounts(lyricsStore.lyrics))
const filterOptions = computed(() => [
  {
    value: 'all',
    label: t('band_space_setlist.lyrics_view.filter_all', { count: counts.value.all })
  },
  {
    value: 'linked',
    label: t('band_space_setlist.lyrics_view.filter_linked', { count: counts.value.linked })
  },
  {
    value: 'unlinked',
    label: t('band_space_setlist.lyrics_view.filter_unlinked', { count: counts.value.unlinked })
  }
])
const visibleLyrics = computed(() =>
  filterLyrics(lyricsStore.lyrics, { query: query.value, filter: filter.value })
)

function contentLabel(item) {
  return item.has_content
    ? t('band_space_setlist.common.lyrics_present')
    : t('band_space_setlist.lyrics_view.no_content')
}

function openDrawer(lyrics, { edit = false } = {}) {
  drawerLyrics.value = lyrics
  drawerEdits.value = edit
  drawerVisible.value = true
}

// Same deep link pattern as the repertoire's ?song=, and the same reason to drop it on close: the
// watcher below re-runs on every list change and would bring the linked lyrics back.
watch(drawerVisible, (open, wasOpen) => {
  if (!wasOpen || open) return
  if (route.query.lyrics) {
    router.replace({ query: { ...route.query, lyrics: undefined } })
  }
})

watch(
  [() => props.focusLyricsId, () => lyricsStore.lyrics],
  ([lyricsId]) => {
    if (!lyricsId || drawerLyrics.value?.id === lyricsId) return
    const lyrics = lyricsStore.lyrics.find((candidate) => candidate.id === lyricsId)
    if (lyrics) openDrawer(lyrics)
  },
  { immediate: true }
)
</script>
