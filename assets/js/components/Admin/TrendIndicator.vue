<template>
  <div
    :class="[
      'flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium',
      isPositive ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
      isNegative ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
      'bg-surface-100 text-surface-600 dark:bg-surface-700 dark:text-surface-400'
    ]"
  >
    <i
      :class="[
        'pi text-xs',
        isPositive ? 'pi-arrow-up' : isNegative ? 'pi-arrow-down' : 'pi-minus'
      ]"
    />
    <span>{{ Math.abs(value) }}%</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: {
    type: Number,
    required: true
  },
  threshold: {
    type: Number,
    default: 5
  }
})

const isPositive = computed(() => props.value > props.threshold)
const isNegative = computed(() => props.value < -props.threshold)
</script>
