<template>
  <!-- The Android app's mark: an empty square to do, a dot while in progress, a tick once done. -->
  <span
    class="inline-flex size-6 shrink-0 items-center justify-center rounded-lg border-2"
    :class="{
      'border-surface-300 dark:border-surface-600': status === 'todo',
      'border-primary-600 dark:border-primary-400': status === 'in_progress',
      'border-teal-700 bg-teal-700 text-white dark:border-teal-400 dark:bg-teal-400 dark:text-surface-950':
        status === 'done'
    }"
    aria-hidden="true"
  >
    <span v-if="status === 'in_progress'" class="size-2 rounded-full bg-primary-600 dark:bg-primary-400" />
    <i v-else-if="status === 'done'" class="pi pi-check text-xs" />
  </span>
</template>

<script setup>
defineProps({
  status: { type: String, required: true }
})
</script>
