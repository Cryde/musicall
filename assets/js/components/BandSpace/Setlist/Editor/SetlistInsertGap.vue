<template>
  <!-- A thin gap between two rows that opens on hover. Pointer only: a gap per row would put four
       tab stops between every two rows, so the keyboard inserts through « Insérer après » in the row
       menu instead. Once « Titre » is picked the gap stays open, and reachable, to show where songs go. -->
  <div
    :class="['group/gap flex items-center gap-2 transition-all', active ? 'h-9' : 'h-2 hover:h-9']"
    :aria-hidden="active ? undefined : 'true'"
  >
    <div :class="['flex-1 h-0.5 rounded bg-primary', active ? 'opacity-100' : 'opacity-0 group-hover/gap:opacity-100']" />
    <div
      :class="[
        'flex items-center gap-1 p-0.5 rounded-lg border border-primary bg-surface-0 dark:bg-surface-900',
        active ? 'opacity-100' : 'opacity-0 group-hover/gap:opacity-100'
      ]"
      role="group"
      :aria-label="t('band_space_setlist.insert_gap.group_aria', { position: position + 1 })"
    >
      <template v-if="active">
        <span class="text-xs px-2">{{ t('band_space_setlist.insert_gap.active_hint') }}</span>
        <Button icon="pi pi-times" size="small" text rounded severity="secondary" :aria-label="t('band_space_setlist.insert_gap.stop_aria')" @click="emit('cancel')" />
      </template>
      <template v-else>
        <span class="text-xs px-2 hidden sm:inline">{{ t('band_space_setlist.insert_gap.insert_here') }}</span>
        <Button :label="t('band_space_setlist.insert_gap.song')" size="small" tabindex="-1" class="!py-0.5 !px-2 !text-xs" @click="emit('insert-song', position)" />
        <Button
          v-for="kind in INTERMISSION_KINDS"
          :key="kind.type"
          :label="kind.label"
          size="small"
          severity="secondary"
          outlined
          tabindex="-1"
          class="!py-0.5 !px-2 !text-xs"
          @click="emit('insert-intermission', kind.type, position)"
        />
      </template>
    </div>
    <div :class="['flex-1 h-0.5 rounded bg-primary', active ? 'opacity-100' : 'opacity-0 group-hover/gap:opacity-100']" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { useI18n } from 'vue-i18n'
import { INTERMISSION_KINDS } from './intermissionKinds.js'

defineProps({
  /** The position an item inserted here takes. */
  position: { type: Number, required: true },
  active: { type: Boolean, default: false }
})

const emit = defineEmits(['insert-song', 'insert-intermission', 'cancel'])
const { t } = useI18n()
</script>
