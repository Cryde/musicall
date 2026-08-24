<template>
  <!-- The line down a set of sibling folders, with a tick into this one's row. Cut short on the last
       sibling so the line ends on it instead of running past into whatever follows the tree.
       16px wide, not 20: the tick runs from 8px to 16px, so at this width it lands exactly on the row it
       points at instead of stopping short of it, and six levels of nesting cost 96px of a 224px sidebar
       rather than 120px. -->
  <div class="relative w-4 shrink-0" aria-hidden="true">
    <span
      class="absolute left-2 top-0 w-px bg-surface-300 dark:bg-surface-600"
      :class="isLast ? 'h-5' : 'h-full'"
    ></span>
    <span class="absolute left-2 top-5 w-2 h-px bg-surface-300 dark:bg-surface-600"></span>
  </div>
</template>

<script setup>
defineProps({
  isLast: { type: Boolean, default: false }
})
</script>
