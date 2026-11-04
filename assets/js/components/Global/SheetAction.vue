<template>
  <RouterLink v-if="to" :to="to" :class="classes">
    <i class="pi" :class="[ICON, icon]" aria-hidden="true" />
    <slot />
  </RouterLink>
  <button v-else type="button" :class="classes" :disabled="disabled">
    <i class="pi" :class="[ICON, icon]" aria-hidden="true" />
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

/** One action of a bottom sheet: a full-width row, an icon then its label, red when it destroys or reports. */
const props = defineProps({
  icon: { type: String, required: true },
  // A link rather than a button.
  to: { type: [Object, String], default: null },
  danger: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false }
})

const ROW =
  'flex w-full cursor-pointer items-center gap-3 px-5 py-3.5 text-left transition-colors hover:bg-surface-100 disabled:cursor-default disabled:opacity-50 dark:hover:bg-surface-800'
const ICON = 'w-5 shrink-0 text-center text-base'

const classes = computed(() => [
  ROW,
  props.danger
    ? 'text-red-700 dark:text-red-400'
    : 'text-surface-900 dark:text-surface-0 [&>.pi]:text-surface-600 dark:[&>.pi]:text-surface-400'
])
</script>
