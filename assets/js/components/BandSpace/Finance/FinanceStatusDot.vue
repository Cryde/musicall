<template>
  <!-- Status conveyed by icon SHAPE + colour (not colour alone, WCAG 1.4.1),
       with an accessible name for assistive tech. -->
  <span role="img" :aria-label="meta.label" :title="meta.label" class="inline-flex flex-shrink-0 leading-none">
    <i :class="['pi', meta.icon, meta.color, 'text-xs']" aria-hidden="true" />
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, default: 'planned' }
})

const STATUS_META = {
  paid: { label: 'Payé', icon: 'pi-check-circle', color: 'text-green-500' },
  committed: { label: 'Engagé', icon: 'pi-clock', color: 'text-orange-500' },
  planned: { label: 'Prévu', icon: 'pi-circle', color: 'text-surface-500 dark:text-surface-400' }
}

const meta = computed(() => STATUS_META[props.status] ?? STATUS_META.planned)
</script>
