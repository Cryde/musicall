<template>
  <div>
    <button
      type="button"
      class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
      :class="repertoireButtonClasses"
      @click="emit('select-repertoire')"
    >
      <i class="pi pi-book text-emerald-600"></i>
      <span class="flex-1 truncate font-medium">Répertoire</span>
      <Badge :value="songsCount" severity="secondary" />
    </button>

    <h2 class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400 px-3 mt-5 mb-2">
      Setlists
    </h2>
    <div v-if="isLoadingSetlists && setlists.length === 0" class="flex flex-col gap-1 px-3">
      <Skeleton v-for="i in 3" :key="i" width="100%" height="1.75rem" borderRadius="0.375rem" />
    </div>
    <div v-else-if="setlists.length === 0" class="text-xs text-surface-400 italic px-3 py-2">
      Aucune setlist pour le moment.
    </div>
    <ul v-else class="flex flex-col gap-1">
      <li v-for="setlist in setlists" :key="setlist.id">
        <button
          type="button"
          class="w-full text-left flex items-center gap-2 px-3 py-2 rounded-lg transition-colors"
          :class="setlistButtonClasses(setlist.id)"
          @click="emit('select-setlist', setlist.id)"
        >
          <i class="pi pi-list text-rose-600"></i>
          <span class="flex-1 truncate">{{ setlist.name }}</span>
          <span class="text-xs text-surface-500 tabular-nums">{{ setlist.items?.length ?? 0 }}</span>
        </button>
      </li>
    </ul>

    <div class="mt-5">
      <Button
        label="Nouvelle setlist"
        icon="pi pi-plus"
        size="small"
        severity="secondary"
        class="w-full"
        disabled
        v-tooltip.top="'La création de setlists arrive dans la prochaine version'"
      />
    </div>
  </div>
</template>

<script setup>
import Badge from 'primevue/badge'
import Button from 'primevue/button'
import Skeleton from 'primevue/skeleton'
import { computed } from 'vue'

const props = defineProps({
  songsCount: { type: Number, required: true },
  setlists: { type: Array, required: true },
  isLoadingSetlists: { type: Boolean, default: false },
  activeView: { type: String, required: true }, // 'repertoire' | 'setlist'
  activeSetlistId: { type: String, default: null }
})

const emit = defineEmits(['select-repertoire', 'select-setlist'])

const repertoireButtonClasses = computed(() =>
  props.activeView === 'repertoire'
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
)

function setlistButtonClasses(id) {
  return props.activeView === 'setlist' && props.activeSetlistId === id
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
}
</script>
