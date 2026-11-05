<template>
  <div
    :data-item-id="row.item.id"
    :class="[
      'flex items-center gap-1.5 rounded-xl border py-1 pr-0.5',
      row.isSong
        ? 'min-h-14 border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900'
        : 'min-h-11 border-dashed border-surface-300 dark:border-surface-600',
      row.item.pending && 'opacity-60'
    ]"
  >
    <span
      v-if="!readonly"
      class="drag-handle flex h-11 w-8.5 shrink-0 cursor-grab touch-none items-center justify-center text-surface-500 dark:text-surface-400"
      aria-hidden="true"
    >
      <!-- A finger drags it; a keyboard or a screen reader moves the row from ⋮, « Monter » / « Descendre ». -->
      <i class="pi pi-bars text-sm" aria-hidden="true" />
    </span>
    <span v-else class="w-3 shrink-0" aria-hidden="true" />

    <template v-if="row.isSong">
      <span class="w-5 shrink-0 text-sm text-surface-600 tabular-nums dark:text-surface-300">{{ row.number }}</span>
      <button type="button" class="flex min-w-0 flex-1 flex-col text-left" @click="emit('open-song', row.item)">
        <span class="truncate font-medium">{{ row.item.song?.title ?? noValue() }}</span>
        <span v-if="row.item.song?.tonality" class="text-[0.8125rem] text-surface-600 dark:text-surface-300">{{ row.item.song.tonality }}</span>
      </button>
      <!-- Straight to the words from the running order, at rehearsal. -->
      <Button
        v-if="row.item.song?.has_lyrics"
        icon="pi pi-align-left"
        text
        rounded
        class="h-11! w-10! shrink-0"
        :aria-label="t('band_space_setlist.phone.read_lyrics_of', { title: row.item.song.title })"
        @click="emit('read-lyrics', row.item)"
      />
    </template>

    <button
      v-else
      type="button"
      class="flex min-w-0 flex-1 items-center gap-2 text-left"
      :aria-label="t('band_space_setlist.programme_row.edit_aria', { label: intermissionLabel ?? kind.label })"
      @click="emit('edit', row.item)"
    >
      <i :class="[kind.icon, kind.color, 'text-sm']" aria-hidden="true" />
      <span :class="['shrink-0 text-[11px] font-bold tracking-wide', kind.color]">{{ kind.tag }}</span>
      <span v-if="intermissionLabel" class="truncate text-sm text-surface-700 dark:text-surface-200">{{ intermissionLabel }}</span>
    </button>

    <!-- The duration over the time elapsed by the end of the row, as the app shows it. -->
    <span class="flex shrink-0 flex-col items-end tabular-nums">
      <button
        v-if="row.missingDuration && !readonly"
        type="button"
        class="rounded-full border border-dashed border-amber-500 px-2 py-0.5 text-xs text-amber-800 dark:text-amber-300"
        :aria-label="t('band_space_setlist.common.add_duration_aria', { title: row.item.song?.title })"
        @click="emit('set-duration', $event, row.item)"
      >
        {{ t('band_space_setlist.common.add_duration') }}
      </button>
      <span v-else class="text-[0.9375rem]" :class="row.isSong ? '' : 'text-surface-600 dark:text-surface-300'">
        {{ formatDuration(row.duration) || noValue() }}
      </span>
      <span v-if="row.isSong" class="text-xs text-surface-500 dark:text-surface-400">
        {{ formatDuration(row.cumulative) || '0:00' }}<template v-if="row.uncertain"> +?</template>
      </span>
    </span>

    <Button
      v-if="!readonly"
      icon="pi pi-ellipsis-v"
      text
      rounded
      severity="secondary"
      class="h-11! w-10! shrink-0"
      aria-haspopup="dialog"
      :aria-label="row.isSong ? t('band_space_setlist.programme_row.song_actions_aria') : t('band_space_setlist.programme_row.intermission_actions_aria')"
      @click="emit('menu', row.item)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { noValue } from '../../../../i18n/formatLocale.js'
import { formatDuration } from '../../../../utils/setlistDuration.js'
import { intermissionKind, isDefaultIntermissionLabel } from './intermissionKinds.js'

/**
 * A row of the running order on a phone, as the app draws it: the handle, the number, the title over
 * its key, a way to its lyrics, the duration over the time elapsed, and ⋮. The whole title opens the
 * song; an intermède opens its own settings, as on the desktop.
 */
const props = defineProps({
  // One entry of programmeRows()
  row: { type: Object, required: true },
  readonly: { type: Boolean, default: false }
})

const emit = defineEmits(['open-song', 'read-lyrics', 'edit', 'menu', 'set-duration'])
const { t } = useI18n()

const kind = computed(() => intermissionKind(props.row.item.type))
const intermissionLabel = computed(() => {
  const label = props.row.item.label?.trim()
  return label && !isDefaultIntermissionLabel(props.row.item.type, label) ? label : null
})
</script>
