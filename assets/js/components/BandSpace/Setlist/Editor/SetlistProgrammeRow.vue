<template>
  <div
    :data-item-id="row.item.id"
    :class="[
      'group flex items-center gap-2 sm:gap-3 px-2 sm:px-3 rounded-xl border transition-colors',
      row.isSong
        ? 'min-h-12 py-1.5 bg-surface-0 dark:bg-surface-800 border-surface-200 dark:border-surface-700 hover:border-surface-400 dark:hover:border-surface-500'
        : 'min-h-10 py-1 border-dashed border-surface-300 dark:border-surface-600',
      row.item.pending && 'opacity-60'
    ]"
  >
    <span
      v-if="!readonly"
      class="drag-handle w-5 flex justify-center text-surface-400 group-hover:text-surface-600 dark:group-hover:text-surface-300 cursor-grab"
      aria-hidden="true"
    >
      <i class="pi pi-bars text-sm" />
    </span>

    <template v-if="row.isSong">
      <span class="w-6 text-sm text-surface-600 dark:text-surface-300 tabular-nums">{{ row.number }}</span>
      <div class="flex-1 min-w-0">
        <!-- Wraps rather than squeezing: on a phone the tags go under the title instead of eating it. -->
        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 min-w-0">
          <button
            type="button"
            class="font-medium truncate max-w-full text-left hover:underline disabled:hover:no-underline"
            :disabled="readonly"
            @click="emit('edit', row.item)"
          >
            {{ row.item.song?.title ?? '—' }}
          </button>
          <span
            v-if="row.item.song?.archive_datetime"
            class="shrink-0 text-xs px-1.5 py-0.5 rounded bg-surface-100 dark:bg-surface-700 text-surface-600 dark:text-surface-300"
          >archivée</span>
          <span
            v-if="columns.tonality && row.item.song?.tonality"
            class="shrink-0 text-xs px-1.5 py-0.5 rounded bg-surface-100 dark:bg-surface-700 text-surface-700 dark:text-surface-200"
            :aria-label="`Tonalité ${row.item.song.tonality}`"
          >{{ row.item.song.tonality }}</span>
          <span v-if="columns.tempo && row.item.song?.tempo" class="shrink-0 text-xs text-surface-600 dark:text-surface-300 tabular-nums">
            {{ row.item.song.tempo }} BPM
          </span>
        </div>
        <SetlistRowDetails v-if="columns.details" :item="row.item" />
      </div>
      <div class="hidden sm:flex items-center gap-2 text-primary shrink-0">
        <i v-if="row.item.song?.has_lyrics" class="pi pi-align-left text-sm" v-tooltip.top="'Paroles et accords'" aria-label="Paroles et accords" />
        <span
          v-if="fileCount > 0"
          class="flex items-center gap-1 text-xs text-surface-600 dark:text-surface-300"
          v-tooltip.top="'Fichiers attachés'"
          :aria-label="`${fileCount} ${fileCount > 1 ? 'fichiers' : 'fichier'}`"
        >
          <i class="pi pi-paperclip text-xs" aria-hidden="true" />{{ fileCount }}
        </span>
      </div>
    </template>

    <template v-else>
      <i :class="[kind.icon, kind.color, 'w-6 text-sm']" aria-hidden="true" />
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2 min-w-0">
          <!-- The tag alone while the label is still the kind's own name, so a fresh MC does not read « MC MC ». -->
          <button
            type="button"
            class="flex items-center gap-2 min-w-0 text-left hover:underline disabled:hover:no-underline"
            :disabled="readonly"
            :aria-label="`Modifier ${intermissionLabel ?? kind.label}`"
            @click="emit('edit', row.item)"
          >
            <span :class="['text-[11px] font-bold tracking-wide shrink-0', kind.color]">{{ kind.tag }}</span>
            <span v-if="intermissionLabel" class="text-sm text-surface-700 dark:text-surface-200 truncate">{{ intermissionLabel }}</span>
          </button>
        </div>
        <SetlistRowDetails v-if="columns.details" :item="row.item" />
      </div>
    </template>

    <div v-if="columns.duration" class="w-16 flex justify-end shrink-0">
      <button
        v-if="row.missingDuration && !readonly"
        type="button"
        class="h-7 w-16 rounded-md border border-dashed border-amber-500 bg-amber-50 dark:bg-amber-950/40 text-amber-800 dark:text-amber-300 text-xs"
        :aria-label="`Ajouter la durée de ${row.item.song?.title}`"
        @click="emit('set-duration', $event, row.item)"
      >
        + durée
      </button>
      <span v-else class="text-sm tabular-nums" :class="row.isSong ? '' : 'text-surface-600 dark:text-surface-300'">
        {{ formatDuration(row.duration) || '—' }}
      </span>
    </div>

    <span v-if="columns.cumulative" class="hidden sm:inline w-16 text-right text-sm text-surface-600 dark:text-surface-300 tabular-nums shrink-0">
      {{ formatDuration(row.cumulative) || '0:00' }}<template v-if="row.uncertain"> +?</template>
    </span>

    <div v-if="!readonly" class="flex justify-end gap-0.5 shrink-0">
      <Button
        icon="pi pi-times"
        severity="secondary"
        text
        rounded
        size="small"
        class="!hidden sm:!inline-flex sm:opacity-0 sm:group-hover:opacity-100 sm:focus:opacity-100"
        aria-label="Retirer de la setlist"
        v-tooltip.top="'Retirer de la setlist'"
        @click="emit('remove', row.item)"
      />
      <Button
        icon="pi pi-ellipsis-v"
        severity="secondary"
        text
        rounded
        size="small"
        :aria-label="row.isSong ? 'Actions du titre' : 'Actions de l’intermède'"
        @click="emit('menu', $event, row.item)"
      />
    </div>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { formatDuration } from '../../../../utils/setlistDuration.js'
import { intermissionKind } from './intermissionKinds.js'
import SetlistRowDetails from './SetlistRowDetails.vue'

/** One line of the running order: a song, or a Pause / MC / Interlude drawn dashed (#1061). */
const props = defineProps({
  /** From programmeRows(). */
  row: { type: Object, required: true },
  columns: { type: Object, required: true },
  fileCount: { type: Number, default: 0 },
  readonly: { type: Boolean, default: false }
})

const emit = defineEmits(['edit', 'remove', 'menu', 'set-duration'])

const kind = computed(() => intermissionKind(props.row.item.type))
const intermissionLabel = computed(() => {
  const label = props.row.item.label?.trim()
  return label && label !== kind.value.label ? label : null
})
</script>
