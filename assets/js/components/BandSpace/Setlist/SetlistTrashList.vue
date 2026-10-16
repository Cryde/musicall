<template>
  <div class="flex flex-col gap-4">
    <div
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700"
    >
      <h2 class="text-sm font-semibold text-surface-800 dark:text-surface-100 mb-1">{{ t('band_space_setlist.trash.title') }}</h2>
      <!-- No countdown here, unlike the files trash: setlists and titles are never purged on a
           timer, so promising a deadline would be a lie. -->
      <p class="text-xs text-surface-600 dark:text-surface-300">
        {{ t('band_space_setlist.trash.intro') }}
      </p>
    </div>

    <div
      class="bg-surface-0 dark:bg-surface-900 rounded-2xl p-4 border border-surface-200 dark:border-surface-700"
    >
      <div v-if="isLoading && isEmpty" class="flex flex-col gap-2">
        <Skeleton v-for="i in 3" :key="i" width="100%" height="3.5rem" borderRadius="0.5rem" />
      </div>

      <div
        v-else-if="isEmpty"
        class="flex flex-col items-center justify-center py-16 text-center text-surface-500 dark:text-surface-400"
      >
        <i class="pi pi-trash text-5xl mb-4" aria-hidden="true"></i>
        <p class="text-sm italic">{{ t('band_space_setlist.trash.empty') }}</p>
      </div>

      <div v-else class="flex flex-col gap-6">
        <section v-if="setlists.length > 0">
          <h3
            class="text-xs font-semibold uppercase tracking-wide text-surface-600 dark:text-surface-300 mb-2"
          >
            {{ t('band_space_setlist.trash.setlists') }}
          </h3>
          <ul class="flex flex-col gap-2">
            <li
              v-for="setlist in setlists"
              :key="setlist.id"
              class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-700"
            >
              <i class="pi pi-list text-lg text-rose-600 shrink-0" aria-hidden="true"></i>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-surface-800 dark:text-surface-100 truncate">
                  {{ setlist.name }}
                </p>
                <span class="text-xs text-surface-600 dark:text-surface-300">
                  {{
                    t(
                      'band_space_setlist.trash.setlist_line',
                      { date: formatDateLong(setlist.archive_datetime) },
                      setlist.items?.length ?? 0
                    )
                  }}
                </span>
              </div>
              <Button
                :label="t('band_space_setlist.trash.restore')"
                icon="pi pi-replay"
                text
                size="small"
                :loading="busyId === setlist.id"
                @click="handleRestoreSetlist(setlist)"
              />
            </li>
          </ul>
        </section>

        <section v-if="songs.length > 0">
          <h3
            class="text-xs font-semibold uppercase tracking-wide text-surface-600 dark:text-surface-300 mb-2"
          >
            {{ t('band_space_setlist.trash.songs') }}
          </h3>
          <ul class="flex flex-col gap-2">
            <li
              v-for="song in songs"
              :key="song.id"
              class="flex items-center gap-3 p-3 rounded-lg border border-surface-200 dark:border-surface-700"
            >
              <i class="pi pi-book text-lg text-emerald-600 shrink-0" aria-hidden="true"></i>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-surface-800 dark:text-surface-100 truncate">
                  {{ song.title }}
                </p>
                <span class="text-xs text-surface-600 dark:text-surface-300">
                  {{ t('band_space_setlist.trash.song_line', { date: formatDateLong(song.archive_datetime) }) }}
                </span>
              </div>
              <Button
                :label="t('band_space_setlist.trash.restore')"
                icon="pi pi-replay"
                text
                size="small"
                :loading="busyId === song.id"
                @click="handleRestoreSong(song)"
              />
            </li>
          </ul>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { useToast } from 'primevue/usetoast'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBandSetlistsStore } from '../../../store/bandSpace/bandSpaceSetlists.js'
import { useBandSongsStore } from '../../../store/bandSpace/bandSpaceSongs.js'
import { formatDateLong } from '../../../utils/date.js'

const props = defineProps({
  bandSpaceId: { type: String, required: true },
  setlists: { type: Array, required: true },
  songs: { type: Array, required: true },
  isLoading: { type: Boolean, default: false }
})

const { t } = useI18n()
const setlistsStore = useBandSetlistsStore()
const songsStore = useBandSongsStore()
const toast = useToast()

const busyId = ref(null)

const isEmpty = computed(() => props.setlists.length === 0 && props.songs.length === 0)

async function handleRestoreSetlist(setlist) {
  busyId.value = setlist.id
  try {
    await setlistsStore.restoreSetlist(props.bandSpaceId, setlist.id)
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.trash.setlist_restored'),
      life: 3000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.common.error'),
      detail: error.message || t('band_space_setlist.trash.setlist_restore_failed'),
      life: 5000
    })
  } finally {
    busyId.value = null
  }
}

async function handleRestoreSong(song) {
  busyId.value = song.id
  try {
    await songsStore.restoreSong(props.bandSpaceId, song.id)
    toast.add({
      severity: 'success',
      summary: t('band_space_setlist.trash.song_restored'),
      life: 3000
    })
  } catch (error) {
    toast.add({
      severity: 'error',
      summary: t('band_space_setlist.common.error'),
      detail: error.message || t('band_space_setlist.trash.song_restore_failed'),
      life: 5000
    })
  } finally {
    busyId.value = null
  }
}
</script>
