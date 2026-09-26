<template>
  <div :class="['lyrics-sheet', large ? 'text-lg' : 'text-sm']">
    <div v-if="showSingers && legend.length > 0" class="flex flex-wrap gap-2 mb-3 text-xs">
      <span v-for="singer in legend" :key="singer.id" class="inline-flex items-center gap-1">
        <span class="lyrics-singer-tag" :style="{ background: singer.color }">{{ singer.name }}</span>
        <span v-if="singer.former" class="text-surface-600 dark:text-surface-300">(ancien membre)</span>
      </span>
    </div>

    <template v-for="(block, index) in sheet" :key="index">
      <div v-if="block.type === 'section'" :class="['lyrics-section', `lyrics-section--${block.kind}`]">
        <div class="text-xs uppercase font-semibold tracking-wide text-surface-600 dark:text-surface-300 mb-1">
          {{ sectionLabel(block) }}
        </div>
        <template v-for="(child, childIndex) in block.content" :key="childIndex">
          <LyricsSheetLeaf :block="child" v-bind="leafProps" />
        </template>
      </div>
      <LyricsSheetLeaf v-else :block="block" v-bind="leafProps" />
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  parse,
  prefersFlats,
  SINGER_ALL,
  sectionLabel,
  singerColors,
  singerIds,
  toSheet,
  transposeKey
} from '../../../../utils/chordpro.js'
import LyricsSheetLeaf from './LyricsSheetLeaf.vue'

/**
 * A song as the band reads it (#1055): chords over the syllable they start on, each passage tinted
 * in its singer's colour with their names in the margin. The PDF prints the same thing, see
 * templates/pdf/song/_sheet.html.twig.
 */
const props = defineProps({
  lyrics: { type: String, default: '' },
  /** From the API, or the roster while editing: [{ id, name, is_former_member }]. */
  singers: { type: Array, default: () => [] },
  tonality: { type: String, default: null },
  /** Semitones, applied to what is shown only. */
  transpose: { type: Number, default: 0 },
  showChords: { type: Boolean, default: true },
  showSingers: { type: Boolean, default: true },
  large: { type: Boolean, default: false }
})

const blocks = computed(() => parse(props.lyrics))
const sheet = computed(() => toSheet(blocks.value))
const colors = computed(() => singerColors(singerIds(blocks.value)))
const flats = computed(() =>
  prefersFlats(transposeKey(props.tonality, props.transpose), props.transpose)
)

function singerFor(id) {
  if (id === SINGER_ALL) return { name: 'Tous', former: false }
  const singer = props.singers.find((candidate) => candidate.id === id)
  return { name: singer?.name ?? 'Membre inconnu', former: singer?.is_former_member ?? false }
}

const legend = computed(() =>
  Object.entries(colors.value).map(([id, color]) => ({ id, color, ...singerFor(id) }))
)

const hasSingers = computed(() => props.showSingers && legend.value.length > 0)

const leafProps = computed(() => ({
  colors: colors.value,
  nameOf: (id) => singerFor(id).name,
  transpose: props.transpose,
  flats: flats.value,
  showChords: props.showChords,
  showSingers: hasSingers.value
}))
</script>
