<template>
  <button
    type="button"
    class="group w-full min-h-11 flex items-center gap-2 pl-2.5 pr-1.5 rounded-lg text-left hover:bg-surface-100 dark:hover:bg-surface-800 disabled:cursor-default disabled:hover:bg-transparent"
    :class="inSet && 'text-surface-600 dark:text-surface-300'"
    :disabled="readonly"
    :aria-label="inSet ? `Ajouter encore ${song.title} à la setlist` : `Ajouter ${song.title} à la setlist`"
    @click="emit('add')"
  >
    <span class="flex-1 min-w-0 truncate text-sm">{{ song.title }}</span>
    <i v-if="song.has_lyrics" class="pi pi-align-left text-xs text-primary" aria-hidden="true" />
    <span v-if="inSet" class="text-xs tabular-nums">#{{ positions.join(', #') }}</span>
    <span v-else class="w-10 text-right text-xs tabular-nums text-surface-600 dark:text-surface-300">
      {{ formatDuration(song.reference_duration) || '—' }}
    </span>
    <span
      v-if="!readonly"
      class="w-7 h-7 flex items-center justify-center rounded-md border border-surface-300 dark:border-surface-600 group-hover:bg-primary group-hover:border-primary group-hover:text-primary-contrast"
      aria-hidden="true"
    >
      <i class="pi pi-plus text-xs" />
    </span>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { formatDuration } from '../../../../utils/setlistDuration.js'

const props = defineProps({
  song: { type: Object, required: true },
  /** Its numbers in the running order, when it is already there. */
  positions: { type: Array, default: () => [] },
  readonly: { type: Boolean, default: false }
})

const emit = defineEmits(['add'])

const inSet = computed(() => props.positions.length > 0)
</script>
