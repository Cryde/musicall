<template>
  <RouterLink
    :to="to"
    :aria-label="ariaLabel"
    class="flex min-h-23 min-w-0 flex-col rounded-2xl border border-surface-200 bg-surface-0 px-3 py-3 transition-colors duration-150 hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:hover:bg-surface-800"
  >
    <span class="mb-2 flex items-center gap-2 text-sm font-semibold text-surface-600 dark:text-surface-300" aria-hidden="true">
      <i :class="['pi text-base text-primary', icon]" />
      <span class="truncate">{{ label }}</span>
    </span>
    <span v-if="isLoading" class="flex flex-col gap-1.5" aria-hidden="true">
      <Skeleton width="70%" height="0.875rem" />
      <Skeleton width="50%" height="0.75rem" />
    </span>
    <template v-else-if="status">
      <span
        :class="[
          'truncate font-bold tabular-nums',
          status.tone === TONE.DANGER ? 'text-red-600 dark:text-red-400' : 'text-surface-900 dark:text-surface-0'
        ]"
        aria-hidden="true"
      >
        {{ status.line1 }}
      </span>
      <span v-if="status.line2" class="truncate text-[0.8125rem] text-surface-500 dark:text-surface-400" aria-hidden="true">
        {{ status.line2 }}
      </span>
    </template>
  </RouterLink>
</template>

<script setup>
import Skeleton from 'primevue/skeleton'
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { TONE } from '../../../utils/bandHomeStatus.js'

/**
 * One module on the phone band home: its name and what is going on in it. Loading, it keeps its size
 * with bars where the lines will be; when its request failed it shows its name only and still opens
 * the module, as in the app.
 */
const props = defineProps({
  to: { type: Object, required: true },
  icon: { type: String, required: true },
  label: { type: String, required: true },
  status: { type: Object, default: null },
  isLoading: { type: Boolean, default: false }
})

const ariaLabel = computed(() =>
  [props.label, props.status?.line1, props.status?.line2].filter(Boolean).join(', ')
)
</script>
