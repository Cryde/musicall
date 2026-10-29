<template>
  <!-- A picture made of markup: one accessible name, its pretend content is not read out. -->
  <div
    role="img"
    :aria-label="label"
    class="overflow-hidden rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-950 shadow-2xl"
  >
    <div class="flex items-center gap-1.5 h-10 px-4 border-b border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-900">
      <span class="size-2.5 rounded-full bg-surface-300 dark:bg-surface-600" />
      <span class="size-2.5 rounded-full bg-surface-300 dark:bg-surface-600" />
      <span class="size-2.5 rounded-full bg-surface-300 dark:bg-surface-600" />
    </div>
    <div class="flex flex-col gap-3 p-5">
      <slot />
    </div>
  </div>
</template>

<script setup>
defineProps({
  label: { type: String, required: true }
})
</script>
