<template>
  <ul class="m-0 p-0 list-none flex flex-wrap gap-2">
    <li v-for="chip in chips" :key="chip.step">
      <button
        v-tooltip.top="t('search.guided.edit')"
        type="button"
        class="inline-flex items-center gap-1.5 min-h-8 rounded-full border border-primary-300 dark:border-primary-400/40 bg-primary-50 dark:bg-primary-400/10 px-3 text-sm text-primary-800 dark:text-primary-100 hover:bg-primary-100 dark:hover:bg-primary-400/20 cursor-pointer"
        :aria-label="t('search.guided.edit_chip', { label: chip.label })"
        @click="$emit('edit', chip.step)"
      >
        <i class="pi pi-pencil text-xs" aria-hidden="true" />
        {{ chip.label }}
      </button>
    </li>
  </ul>
</template>

<script setup>
import { useI18n } from 'vue-i18n'

/** The guided search's answers (#1084), each one a way back to its question. */
defineProps({
  chips: { type: Array, required: true }
})

defineEmits(['edit'])

const { t } = useI18n()
</script>
