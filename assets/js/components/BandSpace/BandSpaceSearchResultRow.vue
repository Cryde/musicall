<template>
  <!-- A div rather than a button on purpose. This is the ARIA combobox pattern: focus stays on the
       caller's input and aria-activedescendant points at the current row, so the rows must not be
       focusable themselves. Pointer move rather than CSS hover, so the mouse and the arrow keys drive
       the one active row instead of lighting up two at once. -->
  <div
    :id="searchOptionId(listboxId, result)"
    role="option"
    :aria-selected="isActive"
    :aria-disabled="unselectable || undefined"
    :class="[
      'flex items-center gap-3 px-3 py-2 rounded-lg border transition-all',
      unselectable ? 'cursor-default opacity-70' : 'cursor-pointer',
      isActive
        ? 'border-primary-200 dark:border-primary-700/50 bg-primary-50 dark:bg-primary-800/75 text-primary'
        : 'border-transparent'
    ]"
    @click="emit('choose', result)"
    @mousemove="emit('activate', result)"
  >
    <!-- Only in a list that mixes kinds without grouping them, the recents (#1046): a group already
         says what kind its rows are. -->
    <span v-if="showKind && kind" class="shrink-0 inline-flex items-center gap-1.5 text-xs text-surface-600 dark:text-surface-300 w-24">
      <i :class="['pi', kind.icon, 'text-xs']" aria-hidden="true" />
      {{ kind.label }}
    </span>
    <span class="flex-1 min-w-0 truncate search-hit-title" v-html="highlightTerm(result.title, term)" />
    <span v-if="result.subtitle" class="shrink-0 text-xs text-surface-600 dark:text-surface-300 truncate max-w-[10rem]">
      {{ result.subtitle }}
    </span>
    <!-- Worded, not just ticked: a mark that says nothing is a mark the member has to guess at, and
         this way it is part of the row's accessible name. -->
    <span v-if="picked" class="shrink-0 inline-flex items-center gap-1 text-xs font-medium">
      <i class="pi pi-check text-xs" aria-hidden="true" />
      {{ pickedLabel }}
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { searchOptionId, searchTypeFor } from '../../utils/bandSpaceSearch.js'
import { highlightTerm } from '../../utils/highlight.js'

const props = defineProps({
  result: { type: Object, required: true },
  listboxId: { type: String, required: true },
  isActive: { type: Boolean, default: false },
  unselectable: { type: Boolean, default: false },
  picked: { type: Boolean, default: false },
  pickedLabel: { type: String, default: '' },
  /** The query, trimmed, to highlight in the title; empty for the recents. */
  term: { type: String, default: '' },
  showKind: { type: Boolean, default: false }
})

const emit = defineEmits(['choose', 'activate'])

const kind = computed(() => searchTypeFor(props.result.type))
</script>

<style scoped>
/* v-html content carries no scope attribute, so the highlight needs :deep to reach it. */
.search-hit-title :deep(mark) {
  background-color: rgba(245, 180, 0, 0.35);
  color: inherit;
  padding: 0 0.1em;
  border-radius: 2px;
}
</style>
