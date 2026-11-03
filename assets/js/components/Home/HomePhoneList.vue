<template>
  <div
    class="overflow-hidden rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900"
    :aria-busy="isLoading"
  >
    <div v-if="isLoading" class="divide-y divide-surface-200 dark:divide-surface-700">
      <div v-for="i in placeholders" :key="i" class="flex items-center gap-3 px-4 py-3">
        <slot name="placeholder">
          <div class="size-10 shrink-0 rounded-full bg-surface-100 dark:bg-surface-800 animate-pulse" />
          <div class="h-9 flex-1 rounded-md bg-surface-100 dark:bg-surface-800 animate-pulse" />
        </slot>
      </div>
    </div>
    <ul v-else-if="!isEmpty" class="m-0 p-0 list-none divide-y divide-surface-200 dark:divide-surface-700">
      <slot />
    </ul>
    <p v-else class="m-0 px-4 py-8 text-center text-sm text-surface-600 dark:text-surface-300">{{ emptyText }}</p>
    <slot name="footer" />
  </div>
</template>

<script setup>
/** A homepage list as a phone shows it: one card of lines, their placeholders, or a word when empty. */
defineProps({
  isLoading: { type: Boolean, default: false },
  isEmpty: { type: Boolean, default: false },
  /** How many placeholder lines stand in while loading. */
  placeholders: { type: Number, required: true },
  emptyText: { type: String, required: true }
})
</script>
