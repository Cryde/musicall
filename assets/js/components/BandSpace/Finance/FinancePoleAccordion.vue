<template>
  <div class="flex justify-end mb-4">
    <Button
      icon="pi pi-plus"
      label="Ajouter une catégorie"
      size="small"
      outlined
      @click="emit('add-category', null)"
    />
  </div>
  <Accordion v-model:value="openPanels" multiple>
    <AccordionPanel v-for="pole in poles" :key="pole.id" :value="pole.id">
      <AccordionHeader>
        <div class="flex items-center justify-between w-full pr-2">
          <span class="font-semibold">{{ pole.name }}</span>
          <span class="text-sm text-surface-500">{{ countEntries(pole) }} entrée{{ countEntries(pole) > 1 ? 's' : '' }}</span>
        </div>
      </AccordionHeader>
      <AccordionContent>
        <!-- Progress bar -->
        <div v-if="poleTotal(pole) > 0" class="mb-4">
          <div class="h-2 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden">
            <div
              class="h-full rounded-full transition-all"
              :class="progressBarColor(pole)"
              :style="{ width: progressPercent(pole) + '%' }"
            ></div>
          </div>
          <p class="text-xs text-surface-500 mt-1">{{ progressPercent(pole) }}% payé</p>
        </div>

        <!-- Entries at pole level -->
        <EntryList
          v-if="(entriesByCategory[pole.id] || []).length > 0 || pole.children.length === 0"
          :entries="entriesByCategory[pole.id] || []"
          :currentMembershipId="currentMembershipId"
          @edit="(entry) => emit('edit-entry', entry)"
        />

        <!-- Child categories -->
        <div v-if="pole.children.length > 0">
          <div v-for="child in pole.children" :key="child.id" class="mb-4 group">
            <div class="flex items-center justify-between mb-2">
              <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300">{{ child.name }}</h4>
              <button
                class="text-xs text-red-500 hover:text-red-700 opacity-0 group-hover:opacity-100 transition-opacity"
                title="Supprimer la catégorie"
                @click="emit('delete-category', child.id)"
              >
                <i class="pi pi-trash"></i>
              </button>
            </div>
            <EntryList
              :entries="entriesByCategory[child.id] || []"
              :currentMembershipId="currentMembershipId"
              @edit="(entry) => emit('edit-entry', entry)"
            />
            <button
              class="mt-1 text-sm text-primary hover:underline flex items-center gap-1"
              @click="emit('add-entry', child.id)"
            >
              <i class="pi pi-plus text-xs"></i>
              Ajouter une entrée
            </button>
          </div>
        </div>

        <!-- Pole-level actions -->
        <div class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700 flex flex-wrap items-center gap-2 sm:gap-3">
          <button
            class="text-xs sm:text-sm text-primary hover:underline flex items-center gap-1"
            @click="emit('add-entry', pole.id)"
          >
            <i class="pi pi-plus text-xs"></i>
            Ajouter une entrée
          </button>
          <button
            class="text-xs sm:text-sm text-surface-500 hover:text-primary hover:underline flex items-center gap-1"
            @click="emit('add-category', pole.id)"
          >
            <i class="pi pi-plus text-xs"></i>
            Ajouter une sous-catégorie
          </button>
          <button
            class="text-xs sm:text-sm text-red-500 hover:text-red-700 flex items-center gap-1 ml-auto"
            @click="emit('delete-category', pole.id)"
          >
            <i class="pi pi-trash text-xs"></i>
            Supprimer la catégorie
          </button>
        </div>
      </AccordionContent>
    </AccordionPanel>
  </Accordion>
</template>

<script setup>
import Accordion from 'primevue/accordion'
import Button from 'primevue/button'
import AccordionContent from 'primevue/accordioncontent'
import AccordionHeader from 'primevue/accordionheader'
import AccordionPanel from 'primevue/accordionpanel'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { effectiveAmount } from '../../../utils/currency.js'
import EntryList from './EntryList.vue'

const STORAGE_KEY_PREFIX = 'finance_open_panels_'

const props = defineProps({
  poles: { type: Array, required: true },
  entriesByCategory: { type: Object, required: true },
  currentMembershipId: { type: String, default: null }
})

const emit = defineEmits(['add-entry', 'edit-entry', 'add-category', 'delete-category'])

const route = useRoute()
const storageKey = STORAGE_KEY_PREFIX + route.params.id

function loadOpenPanels() {
  try {
    const stored = localStorage.getItem(storageKey)
    return stored ? JSON.parse(stored) : []
  } catch {
    return []
  }
}

const openPanels = ref(loadOpenPanels())

watch(openPanels, (value) => {
  localStorage.setItem(storageKey, JSON.stringify(value))
}, { deep: true })

const poleStats = computed(() => {
  const stats = new Map()
  for (const pole of props.poles) {
    const entries = [...(props.entriesByCategory[pole.id] || [])]
    for (const child of pole.children) {
      entries.push(...(props.entriesByCategory[child.id] || []))
    }
    const bandEntries = entries.filter((e) => e.scope !== 'personal')
    const total = bandEntries.reduce((sum, e) => sum + effectiveAmount(e), 0)
    const paid = bandEntries.filter((e) => e.status === 'paid').reduce((sum, e) => sum + effectiveAmount(e), 0)
    const percent = total === 0 ? 0 : Math.min(Math.round((paid / total) * 100), 100)
    stats.set(pole.id, { count: entries.length, total, paid, percent })
  }
  return stats
})

function countEntries(pole) {
  return poleStats.value.get(pole.id)?.count ?? 0
}

function poleTotal(pole) {
  return poleStats.value.get(pole.id)?.total ?? 0
}

function progressPercent(pole) {
  return poleStats.value.get(pole.id)?.percent ?? 0
}

function progressBarColor(pole) {
  const stats = poleStats.value.get(pole.id)
  if (!stats) return 'bg-blue-500'
  if (stats.percent >= 100) return 'bg-green-500'
  if (stats.paid > stats.total) return 'bg-red-500'
  return 'bg-blue-500'
}
</script>
