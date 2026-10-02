<template>
  <section
    v-if="readiness.total > 0"
    :aria-labelledby="titleId"
    class="flex flex-col gap-2 rounded-lg border border-surface-200 dark:border-surface-700 bg-surface-50 dark:bg-surface-800 p-3"
  >
    <div class="flex items-baseline justify-between gap-2 text-sm">
      <h3 :id="titleId" class="font-semibold">Prêt à envoyer</h3>
      <span class="tabular-nums text-surface-600 dark:text-surface-300">
        <span aria-hidden="true">{{ readiness.ready }} / {{ readiness.total }}</span>
        <!-- Worded so nothing has to agree with the count: « 1 sur 1 sections prêtes » was not French. -->
        <span class="sr-only">sections prêtes : {{ readiness.ready }} sur {{ readiness.total }}</span>
      </span>
    </div>
    <div class="h-1.5 rounded-full bg-surface-200 dark:bg-surface-700" aria-hidden="true">
      <div
        class="h-1.5 rounded-full transition-[width]"
        :class="isReady ? 'bg-teal-600 dark:bg-teal-400' : 'bg-amber-500 dark:bg-amber-400'"
        :style="{ width: `${(readiness.ready / readiness.total) * 100}%` }"
      />
    </div>

    <p v-if="isReady" class="flex items-center gap-2 text-xs text-surface-700 dark:text-surface-200">
      <i class="pi pi-check text-teal-700 dark:text-teal-300" aria-hidden="true" />
      Tout ce qui est visible dans le PDF est complet.
    </p>
    <!-- Each line opens the section it is about: the card says what to fix and gets you there. -->
    <ul v-else class="m-0 p-0 list-none flex flex-col gap-1 text-xs leading-snug text-surface-700 dark:text-surface-200">
      <li v-if="readiness.empty.length > 0" class="px-1 py-0.5">
        <span class="font-medium">{{ readiness.empty.length > 1 ? 'Sections vides' : 'Section vide' }}</span>&nbsp;:
        <template v-for="(section, index) in readiness.empty" :key="section.itemId">
          <button
            type="button"
            class="underline decoration-dotted underline-offset-2 hover:decoration-solid cursor-pointer"
            @click="emit('select', section.itemId)"
          >{{ section.title }}</button>{{ index < readiness.empty.length - 1 ? ', ' : '.' }}
        </template>
      </li>
      <li v-for="problem in readiness.problems" :key="problem.itemId">
        <button
          type="button"
          class="w-full text-left rounded px-1 py-0.5 hover:bg-surface-100 dark:hover:bg-surface-700 cursor-pointer"
          @click="emit('select', problem.itemId)"
        >
          <span class="font-medium">{{ problem.title }}</span>&nbsp;: {{ problem.messages.join(', ') }}.
        </button>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { computed, useId } from 'vue'
import { riderReadiness } from '../../../utils/riderCompleteness.js'

/** The « Prêt à envoyer » card under the outline (#1093), from the sections as last saved. */
const props = defineProps({
  items: { type: Array, required: true }
})

const emit = defineEmits(['select'])

// Rendered once per layout, so the heading id cannot be fixed.
const titleId = useId()

const readiness = computed(() => riderReadiness(props.items))
const isReady = computed(() => readiness.value.ready === readiness.value.total)
</script>
