<template>
  <div
    class="bg-surface-0 dark:bg-surface-900 border border-surface-200 dark:border-surface-700 rounded-xl p-3 flex items-center gap-3 cursor-pointer hover:border-primary"
    :data-item-id="item.id"
    @click="emit('edit', item)"
  >
    <div class="text-surface-400 text-sm font-semibold tabular-nums w-8 text-center">
      {{ item.position + 1 }}
    </div>

    <i :class="typeIcon" class="text-lg shrink-0"></i>

    <div class="flex-1 min-w-0">
      <div class="font-medium truncate flex items-center gap-2">
        {{ displayTitle }}
        <span
          v-if="item.song?.archive_datetime"
          v-tooltip.top="'Cette chanson est archivée'"
          class="text-xs px-1.5 py-0.5 rounded bg-surface-100 dark:bg-surface-800 text-surface-500"
        >archivée</span>
      </div>
      <div class="text-xs text-surface-500 flex items-center gap-2 flex-wrap mt-0.5">
        <span v-if="isSong && item.song?.tonality">{{ item.song.tonality }}</span>
        <span v-if="isSong && item.song?.tempo">·&nbsp;{{ item.song.tempo }} BPM</span>
        <span v-if="item.transition" class="text-rose-500">→ {{ item.transition }}</span>
        <span v-if="item.note" class="italic text-surface-400 truncate">{{ item.note }}</span>
      </div>
    </div>

    <div class="text-right shrink-0">
      <div class="text-sm tabular-nums">{{ formattedDuration || '—' }}</div>
      <div
        v-if="hasOverride"
        class="text-[10px] text-amber-600 uppercase tracking-wide"
        v-tooltip.top="`Durée surchargée (référence : ${formatSeconds(item.song?.reference_duration)})`"
      >
        surch.
      </div>
    </div>

    <Button
      icon="pi pi-ellipsis-v"
      severity="secondary"
      text
      rounded
      size="small"
      class="shrink-0"
      aria-label="Actions"
      @click.stop="emit('open-menu', $event, item)"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import { computed } from 'vue'

const props = defineProps({
  item: { type: Object, required: true }
})

const emit = defineEmits(['edit', 'open-menu'])

const isSong = computed(() => props.item.type === 'song')

const typeIcon = computed(() => {
  switch (props.item.type) {
    case 'song':
      return 'pi pi-music text-emerald-600'
    case 'interlude':
      return 'pi pi-volume-up text-sky-600'
    case 'break':
      return 'pi pi-pause text-amber-600'
    case 'talk':
      return 'pi pi-microphone text-purple-600'
    default:
      return 'pi pi-circle'
  }
})

const displayTitle = computed(() => {
  if (isSong.value && props.item.song) return props.item.song.title
  return props.item.label || '—'
})

const effectiveDuration = computed(
  () => props.item.duration_override ?? props.item.song?.reference_duration ?? null
)

const hasOverride = computed(
  () => props.item.duration_override !== null && props.item.duration_override !== undefined
)

function formatSeconds(seconds) {
  if (!seconds) return '—'
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${m}′${String(s).padStart(2, '0')}″`
}

const formattedDuration = computed(() => formatSeconds(effectiveDuration.value))
</script>
