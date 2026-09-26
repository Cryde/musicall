<template>
  <div class="flex flex-col min-h-0 h-full">
    <div class="flex items-center gap-2 px-4 pt-4 pb-3">
      <i class="pi pi-book text-surface-600 dark:text-surface-300" aria-hidden="true" />
      <h2 class="flex-1 m-0 text-base font-semibold">Répertoire</h2>
      <Button
        v-if="closable"
        icon="pi pi-times"
        severity="secondary"
        text
        rounded
        size="small"
        aria-label="Masquer le répertoire"
        @click="emit('close')"
      />
    </div>

    <div class="px-4 pb-3">
      <IconField>
        <InputIcon class="pi pi-search" />
        <InputText v-model="query" type="search" placeholder="Rechercher un titre…" aria-label="Rechercher dans le répertoire" class="w-full" />
      </IconField>
    </div>

    <p v-if="insertHint" class="mx-4 mb-2 text-xs rounded-lg px-2 py-1.5 bg-primary-50 dark:bg-primary-900/40 text-primary-800 dark:text-primary-100">
      {{ insertHint }}
    </p>

    <div class="flex-1 min-h-0 overflow-y-auto pb-2">
      <h3 class="px-4 pt-1 pb-1.5 m-0 text-[11px] font-semibold tracking-wide uppercase text-surface-600 dark:text-surface-300">
        Disponibles · {{ available.length }}
      </h3>
      <p v-if="available.length === 0" class="px-4 text-xs italic text-surface-600 dark:text-surface-300 m-0">
        {{ query ? 'Aucun titre ne correspond.' : 'Tout le répertoire est dans la setlist.' }}
      </p>
      <!-- A drag source only: dropping a song on the setlist inserts it where it lands, and the list
           here is never reordered or emptied by a drag. -->
      <VueDraggable
        :model-value="available"
        :group="{ name: DRAG_GROUP, pull: 'clone', put: false }"
        :sort="false"
        :clone="asPendingItem"
        :disabled="readonly"
        class="flex flex-col px-2"
      >
        <SetlistRepertoireEntry
          v-for="song in available"
          :key="song.id"
          :song="song"
          :readonly="readonly"
          @add="emit('add', song)"
        />
      </VueDraggable>

      <template v-if="inSetlist.length > 0">
        <h3 class="flex items-center gap-1.5 px-4 pt-4 pb-1.5 m-0 text-[11px] font-semibold tracking-wide uppercase text-surface-600 dark:text-surface-300">
          <i class="pi pi-check-circle text-[11px]" aria-hidden="true" />
          Déjà dans la setlist · {{ inSetlist.length }}
        </h3>
        <VueDraggable
          :model-value="inSetlist"
          :group="{ name: DRAG_GROUP, pull: 'clone', put: false }"
          :sort="false"
          :clone="asPendingItem"
          :disabled="readonly"
          class="flex flex-col px-2"
        >
          <SetlistRepertoireEntry
            v-for="song in inSetlist"
            :key="song.id"
            :song="song"
            :positions="positionsOf(song.id)"
            :readonly="readonly"
            @add="emit('add', song)"
          />
        </VueDraggable>
      </template>
    </div>

    <div v-if="!readonly" class="p-3 border-t border-surface-200 dark:border-surface-700">
      <Button
        label="Nouveau titre (ajouté au répertoire)"
        icon="pi pi-plus"
        severity="secondary"
        outlined
        size="small"
        class="w-full"
        @click="emit('create-song')"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import { computed, ref } from 'vue'
import { VueDraggable } from 'vue-draggable-plus'
import SetlistRepertoireEntry from './SetlistRepertoireEntry.vue'
import { DRAG_GROUP, pendingSongItem } from './setlistDrag.js'

/**
 * The band's songs beside the running order (#1061): a click adds one at the insert point or at the
 * end, a drag drops it where it lands. A song already in the set stays addable, for an encore.
 */
const props = defineProps({
  songs: { type: Array, required: true },
  items: { type: Array, required: true },
  insertHint: { type: String, default: null },
  readonly: { type: Boolean, default: false },
  closable: { type: Boolean, default: false }
})

const emit = defineEmits(['add', 'create-song', 'close'])

const query = ref('')

const matching = computed(() => {
  const term = query.value.trim().toLowerCase()
  return term ? props.songs.filter((song) => song.title.toLowerCase().includes(term)) : props.songs
})

const songIdsInSet = computed(
  () =>
    new Set(
      props.items.filter((item) => item.type === 'song' && item.song).map((item) => item.song.id)
    )
)

const available = computed(() => matching.value.filter((song) => !songIdsInSet.value.has(song.id)))
const inSetlist = computed(() => matching.value.filter((song) => songIdsInSet.value.has(song.id)))

// Song numbers, as the running order counts them: intermèdes are not numbered.
const songNumbers = computed(() => {
  const numbers = new Map()
  let number = 0
  for (const item of props.items) {
    if (item.type !== 'song') continue
    number += 1
    if (item.song) numbers.set(item.song.id, [...(numbers.get(item.song.id) ?? []), number])
  }
  return numbers
})

function positionsOf(songId) {
  return songNumbers.value.get(songId) ?? []
}

function asPendingItem(song) {
  return pendingSongItem(song)
}
</script>
