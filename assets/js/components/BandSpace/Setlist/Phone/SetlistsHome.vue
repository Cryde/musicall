<template>
  <div class="-mx-2 pb-24">
    <h1 class="mb-3 px-2 text-2xl font-semibold">{{ t('band_space_setlist.sidebar.setlists') }}</h1>

    <div class="grid grid-cols-2 gap-2.5">
      <button type="button" :class="TILE" @click="emit('select-repertoire')">
        <i class="pi pi-book text-lg text-primary" aria-hidden="true" />
        <span class="min-w-0">
          <span class="block text-[0.9375rem] font-semibold">{{ t('band_space_setlist.common.repertoire') }}</span>
          <span class="text-[0.8125rem] text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.sidebar.song_count', songsCount) }}</span>
        </span>
      </button>
      <button type="button" :class="TILE" @click="emit('select-lyrics')">
        <i class="pi pi-align-left text-lg text-primary" aria-hidden="true" />
        <span class="min-w-0">
          <span class="block text-[0.9375rem] font-semibold">{{ t('band_space_setlist.sidebar.lyrics') }}</span>
          <span class="text-[0.8125rem] text-surface-600 dark:text-surface-300">{{ t('band_space_setlist.phone.lyrics_count', lyricsCount) }}</span>
        </span>
      </button>
    </div>

    <h2 class="mx-2 mt-5 mb-2 text-[0.8125rem] font-semibold text-surface-600 dark:text-surface-300">
      {{ t('band_space_setlist.sidebar.setlists') }}
    </h2>
    <div v-if="isLoading && setlists.length === 0" class="flex flex-col gap-2">
      <Skeleton v-for="i in 2" :key="i" width="100%" height="4rem" border-radius="1rem" />
    </div>
    <p v-else-if="setlists.length === 0" class="mx-2 text-sm text-surface-600 italic dark:text-surface-300">
      {{ t('band_space_setlist.sidebar.empty') }}
    </p>
    <ul v-else class="m-0 flex list-none flex-col gap-2 p-0">
      <li
        v-for="setlist in setlists"
        :key="setlist.id"
        class="flex items-center gap-2 rounded-2xl border border-surface-200 bg-surface-0 py-2 pr-1.5 pl-4 dark:border-surface-700 dark:bg-surface-900"
      >
        <button type="button" class="min-w-0 flex-1 text-left" @click="emit('select-setlist', setlist.id)">
          <span class="block truncate font-semibold">{{ setlist.name }}</span>
          <span class="text-sm text-surface-600 tabular-nums dark:text-surface-300">{{ setlistLine(setlist, t) }}</span>
        </button>
        <!-- Straight to the stage from the list, as the app does: no need to open the set first. -->
        <Button
          icon="pi pi-play-circle"
          text
          rounded
          :disabled="(setlist.items ?? []).length === 0"
          class="h-11! w-11! shrink-0 [&_.pi]:text-2xl!"
          :aria-label="t('band_space_setlist.phone.live_aria', { name: setlist.name })"
          @click="openLive(setlist.id)"
        />
      </li>
    </ul>

    <button
      type="button"
      class="mt-2 flex min-h-13 w-full items-center gap-3 rounded-[14px] bg-surface-50 px-4 text-[0.9375rem] text-surface-800 dark:bg-surface-950 dark:text-surface-100"
      @click="emit('select-trash')"
    >
      <i class="pi pi-trash" aria-hidden="true" />
      {{ t('band_space_setlist.sidebar.trash') }}
      <span class="ml-auto text-surface-500 tabular-nums dark:text-surface-400">{{ trashCount }}</span>
    </button>

    <Button
      :label="t('band_space_setlist.sidebar.new_setlist')"
      icon="pi pi-plus"
      rounded
      class="fixed! right-5 bottom-[calc(1.5rem+env(safe-area-inset-bottom))] z-10 shadow-lg"
      @click="emit('new-setlist')"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { setlistLine } from '../setlistLine.js'

/**
 * The phone's way into the module, as the Android app opens it: the repertoire and the lyrics as two
 * tiles, then every setlist with a ▶ straight to Mode Live, then the trash.
 */
const props = defineProps({
  bandSpaceId: { type: String, required: true },
  songsCount: { type: Number, default: 0 },
  lyricsCount: { type: Number, default: 0 },
  setlists: { type: Array, default: () => [] },
  isLoading: { type: Boolean, default: false },
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
const router = useRouter()

const TILE =
  'flex min-h-16 items-center gap-2.5 rounded-2xl bg-primary-50 px-3 py-2.5 text-left text-surface-900 dark:bg-primary-400/15 dark:text-surface-0'

function openLive(setlistId) {
  router.push({
    name: 'app_band_setlist_live',
    params: { bandSpaceId: props.bandSpaceId, setlistId }
  })
}
</script>
