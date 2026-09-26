<template>
  <NodeViewWrapper as="section" :class="['lyrics-section', `lyrics-section--${node.attrs.kind}`]">
    <div class="flex items-center gap-2 mb-1" contenteditable="false">
      <select
        :value="node.attrs.kind"
        class="text-xs uppercase font-semibold tracking-wide bg-transparent border-0 text-surface-600 dark:text-surface-300 cursor-pointer p-0"
        aria-label="Type de section"
        @change="updateAttributes({ kind: $event.target.value })"
      >
        <option v-for="kind in SECTION_KINDS" :key="kind.value" :value="kind.value">{{ kind.label }}</option>
      </select>
      <input
        :value="node.attrs.label"
        :placeholder="placeholder"
        class="text-xs bg-transparent border-0 border-b border-dashed border-surface-300 dark:border-surface-600 focus:outline-none text-surface-700 dark:text-surface-200 w-40"
        aria-label="Nom de la section"
        @input="updateAttributes({ label: $event.target.value })"
      />
      <button
        type="button"
        class="ml-auto text-xs text-surface-500 hover:text-surface-800 dark:hover:text-surface-100"
        aria-label="Retirer la section, garder les lignes"
        @click="unwrap"
      >
        <i class="pi pi-times text-xs" aria-hidden="true" />
      </button>
    </div>
    <NodeViewContent />
  </NodeViewWrapper>
</template>

<script setup>
import { NodeViewContent, NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3'
import { computed } from 'vue'
import { SECTION_KINDS } from '../../../../utils/chordpro.js'

const props = defineProps(nodeViewProps)

const placeholder = computed(
  () => SECTION_KINDS.find((kind) => kind.value === props.node.attrs.kind)?.label ?? ''
)

/** Lifts the lines out rather than deleting them: taking the frame off must not take the words with it. */
function unwrap() {
  const pos = props.getPos()
  props.editor
    .chain()
    .focus()
    .setTextSelection(pos + 2)
    .lift(props.node.type.name)
    .run()
}
</script>
