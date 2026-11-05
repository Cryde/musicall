<template>
  <BottomSheet v-model:visible="visible" :title="t('band_space_setlist.phone.add_songs')">
    <p
      v-if="hint"
      class="mx-4 mb-2 flex items-center gap-2 rounded-[10px] bg-primary-50 px-3 py-2 text-[0.8125rem] text-primary-800 dark:bg-primary-400/15 dark:text-primary-200"
    >
      <i class="pi pi-arrow-down-right text-xs" aria-hidden="true" />
      {{ hint }}
    </p>
    <IconField icon-position="left" class="mx-4 mb-2">
      <InputIcon class="pi pi-search" />
      <InputText
        v-model="query"
        type="search"
        :placeholder="t('band_space_setlist.common.search_song_placeholder')"
        :aria-label="t('band_space_setlist.common.search_repertoire_aria')"
        class="h-11 w-full !rounded-xl"
      />
    </IconField>

    <template v-for="group in groups" :key="group.key">
      <h3 v-if="group.songs.length > 0" class="px-5 pt-2 pb-1 text-[0.8125rem] font-semibold text-surface-600 dark:text-surface-300">
        {{ group.title }}
      </h3>
      <label
        v-for="song in group.songs"
        :key="song.id"
        class="flex min-h-13 cursor-pointer items-center gap-3 border-surface-200 pr-3 pl-5 not-first-of-type:border-t dark:border-surface-700"
      >
        <Checkbox :model-value="ticked.has(song.id)" :binary="true" @update:model-value="toggle(song.id)" />
        <span class="flex min-w-0 flex-1 flex-col">
          <span class="truncate text-[0.9375rem]">{{ song.title }}</span>
          <span class="text-[0.8125rem] text-surface-600 dark:text-surface-300">{{ songFacts(song) }}</span>
        </span>
      </label>
    </template>
    <p v-if="groups.every((group) => group.songs.length === 0)" class="px-5 py-3 text-sm text-surface-600 dark:text-surface-300">
      {{ t('band_space_setlist.common.no_matching_song') }}
    </p>

    <template #footer>
      <Button
        :label="t('band_space_setlist.phone.add_count', { count: ticked.size })"
        :disabled="ticked.size === 0"
        :loading="adding"
        fluid
        @click="handleAdd"
      />
    </template>
  </BottomSheet>
</template>

<script setup>
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { formatDuration } from '../../../../utils/setlistDuration.js'
import { splitRepertoire } from '../../../../utils/setlistRepertoireSplit.js'
import BottomSheet from '../../../Global/BottomSheet.vue'

/**
 * « Ajouter des titres » on a phone: several songs ticked, then added in one request where the set
 * is being filled (the bulk add the repertoire's selection uses), rather than one tap and one request
 * per song as the desktop panel does.
 */
const props = defineProps({
  songs: { type: Array, required: true },
  items: { type: Array, required: true },
  // « Les titres s'ajoutent après « X » », or null at the end of the set
  hint: { type: String, default: null },
  // Resolves once the songs are in; the sheet closes then
  add: { type: Function, required: true }
})

const visible = defineModel('visible', { type: Boolean, default: false })
const { t } = useI18n()

const query = ref('')
const ticked = ref(new Set())
const adding = ref(false)

watch(visible, (open) => {
  if (!open) return
  query.value = ''
  ticked.value = new Set()
})

const groups = computed(() => {
  const { available, inSetlist } = splitRepertoire(props.songs, props.items, query.value)
  return [
    {
      key: 'available',
      title: t('band_space_setlist.repertoire_panel.available', { count: available.length }),
      songs: available
    },
    {
      key: 'in-setlist',
      title: t('band_space_setlist.repertoire_panel.in_setlist', { count: inSetlist.length }),
      songs: inSetlist
    }
  ]
})

function toggle(id) {
  const next = new Set(ticked.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  ticked.value = next
}

// « Am · 3:58 »
function songFacts(song) {
  return [song.tonality, formatDuration(song.reference_duration)].filter(Boolean).join(' · ')
}

// In repertoire order, whatever order they were ticked in, as the desktop's selection adds them.
async function handleAdd() {
  const ids = props.songs.filter((song) => ticked.value.has(song.id)).map((song) => song.id)
  adding.value = true
  try {
    if (await props.add(ids)) visible.value = false
  } finally {
    adding.value = false
  }
}
</script>
