<template>
  <div class="flex items-center gap-3">
    <span :class="iconContainerClass">
      <i :class="iconClass" />
    </span>
    <p :class="textClass">
      <slot />
    </p>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  soon: {
    type: Boolean,
    default: false
  }
})

const iconContainerClass = computed(() => [
  'w-8 h-8 inline-flex items-center justify-center rounded-full shrink-0 border',
  props.soon
    ? 'bg-surface-100 dark:bg-surface-700 border-surface-200 dark:border-surface-600'
    : 'bg-primary-50 dark:bg-primary-400/30 border-primary-100 dark:border-primary-400/40'
])

const iconClass = computed(() => [
  'text-xl leading-normal',
  props.soon
    ? 'pi pi-clock text-surface-400 dark:text-surface-500'
    : 'pi pi-check text-primary-800 dark:text-primary-300'
])

const textClass = computed(() => [
  'leading-6 text-lg',
  props.soon
    ? 'text-surface-400 dark:text-surface-500 italic'
    : 'text-surface-600 dark:text-surface-400'
])
</script>
