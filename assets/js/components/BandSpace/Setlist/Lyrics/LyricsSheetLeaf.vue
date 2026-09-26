<template>
  <p v-if="block.type === 'comment'" class="lyrics-comment">{{ block.text }}</p>
  <p v-else-if="block.type === 'directive'" class="lyrics-directive">{{ block.raw }}</p>
  <div v-else class="lyrics-sheet-line">
    <span v-if="showSingers" class="lyrics-sheet-gutter">
      <span
        v-for="id in block.singerSets.flat()"
        :key="id"
        class="lyrics-singer-tag"
        :style="{ background: colors[id] }"
      >{{ nameOf(id) }}</span>
    </span>
    <span class="flex flex-wrap">
      <span v-for="(chunk, index) in block.chunks" :key="index" class="inline-flex flex-col whitespace-pre">
        <span v-if="hasChords" class="lyrics-sheet-chord">{{ chordsOf(chunk) || ' ' }}</span>
        <span>
          <span
            v-for="(segment, segmentIndex) in chunk.segments"
            :key="segmentIndex"
            :style="showSingers && segment.singers ? tint(segment.singers) : null"
          >{{ segment.text }}</span>
          <template v-if="chunk.segments.length === 0">&#8203;</template>
        </span>
      </span>
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { singerTint, transposeChord } from '../../../../utils/chordpro.js'

const props = defineProps({
  block: { type: Object, required: true },
  colors: { type: Object, required: true },
  nameOf: { type: Function, required: true },
  transpose: { type: Number, default: 0 },
  flats: { type: Boolean, default: false },
  showChords: { type: Boolean, default: true },
  showSingers: { type: Boolean, default: true }
})

const hasChords = computed(
  () => props.showChords && props.block.chunks?.some((chunk) => chunk.chords.length > 0)
)

function chordsOf(chunk) {
  return chunk.chords.map((name) => transposeChord(name, props.transpose, props.flats)).join(' ')
}

function tint(singers) {
  return { background: singerTint(singers, props.colors) }
}
</script>
