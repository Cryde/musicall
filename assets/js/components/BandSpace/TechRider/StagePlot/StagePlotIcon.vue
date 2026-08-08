<template>
  <span class="stage-plot-icon" :style="style" aria-hidden="true">
    <span v-if="symbol" v-html="symbol" />
    <img v-else :src="imageUrl" :alt="''" />
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { SYMBOL_STROKE_WIDTH } from '../../../../constants/stagePlot.js'

/**
 * Inlined at build time rather than fetched. An SVG behind an <img> is an isolated document that
 * the page's colour cannot reach, so a referenced symbol would render black on every surface.
 *
 * Icons still on their placeholder PNG have no entry here and fall through to imageUrl.
 */
const SYMBOL_DIRECTORY = '../../../../../icons/stage_plot'
const symbols = import.meta.glob('../../../../../icons/stage_plot/*.svg', {
  query: '?raw',
  import: 'default',
  eager: true
})

const props = defineProps({
  slug: { type: String, required: true },
  imageUrl: { type: String, default: '' },
  // Resolved by the caller, since only the canvas has an element to take a colour from.
  colour: { type: String, default: null }
})

const symbol = computed(() => symbols[`${SYMBOL_DIRECTORY}/${props.slug}.svg`] ?? null)

const style = computed(() => ({
  color: props.colour ?? undefined,
  '--sp-stroke': SYMBOL_STROKE_WIDTH
}))
</script>

<style scoped>
/* Width comes from whatever class the caller puts on this component, and the symbol fills it. */
.stage-plot-icon {
  display: block;
}

.stage-plot-icon > *,
.stage-plot-icon :deep(svg) {
  display: block;
  width: 100%;
  height: auto;
}
</style>
