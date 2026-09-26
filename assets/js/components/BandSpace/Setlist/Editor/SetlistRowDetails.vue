<template>
  <div v-if="item.transition || item.note" class="flex items-center gap-2 text-xs min-w-0 mt-0.5">
    <span v-if="item.transition" class="shrink-0 text-rose-700 dark:text-rose-300">→ {{ item.transition }}</span>
    <span v-if="item.note" class="italic text-surface-600 dark:text-surface-300 truncate">{{ item.note }}</span>
  </div>
</template>

<script setup>
/** The transition and note under a row's title, the « Transitions et notes » column. */
defineProps({
  item: { type: Object, required: true }
})
</script>
