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
        <div class="flex flex-wrap items-center justify-between w-full pr-2 gap-x-3 gap-y-1">
          <span class="font-semibold min-w-0 truncate">{{ pole.name }}</span>
          <span class="flex items-baseline gap-2 sm:gap-3 shrink-0">
            <FinanceTypeTotals :totals="poleTotals(pole)" class="text-sm font-semibold" />
            <span class="text-xs text-surface-500 dark:text-surface-400">{{ countEntries(pole) }} entrée{{ countEntries(pole) > 1 ? 's' : '' }}</span>
          </span>
        </div>
      </AccordionHeader>
      <AccordionContent>
        <!-- Share paid, one bar per type: money in and money out are never added up -->
        <div v-if="progressRows(pole).length > 0" class="mb-4 flex flex-col gap-2">
          <div v-for="row in progressRows(pole)" :key="row.label">
            <div class="h-2 rounded-full bg-surface-200 dark:bg-surface-700 overflow-hidden">
              <div
                class="h-full rounded-full transition-all"
                :class="row.percent >= 100 ? 'bg-green-500' : 'bg-blue-500'"
                :style="{ width: row.percent + '%' }"
              ></div>
            </div>
            <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ row.label }} · {{ row.percent }}% payé</p>
          </div>
        </div>

        <!-- Entries at pole level (always rendered so the pole itself is a drop target, even when it
             only holds subcategories) -->
        <EntryList
          :entries="entriesByCategory[pole.id] || []"
          :categoryId="pole.id"
          :currentMembershipId="currentMembershipId"
          :hideEmptyState="pole.children.length > 0"
          @edit="(entry) => emit('edit-entry', entry)"
          @move="(entryId, toCategoryId) => emit('move-entry', entryId, toCategoryId)"
        />

        <!-- Child categories, linked by a tree rail so they read clearly as nested under the pole -->
        <div v-if="pole.children.length > 0">
          <div
            v-for="(child, index) in pole.children"
            :key="child.id"
            class="group flex"
          >
            <!-- Tree rail: a vertical line connecting the subcategories, with a tick into each row -->
            <div class="relative w-5 shrink-0" aria-hidden="true">
              <span
                class="absolute left-2 top-0 w-px bg-surface-300 dark:bg-surface-600"
                :class="index === pole.children.length - 1 ? 'h-3' : 'h-full'"
              ></span>
              <span class="absolute left-2 top-3 w-2 h-px bg-surface-300 dark:bg-surface-600"></span>
            </div>

            <div class="min-w-0 flex-1 pb-4">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1 mb-2">
                <template v-if="editingId === child.id">
                  <InputText
                    v-model="editingName"
                    class="flex-1 text-sm"
                    size="small"
                    @keydown.enter="saveRename(child)"
                    @keydown.escape="cancelRename"
                  />
                  <Button icon="pi pi-check" aria-label="Valider" text rounded size="small" @click="saveRename(child)" />
                  <Button icon="pi pi-times" aria-label="Annuler" text rounded size="small" @click="cancelRename" />
                </template>
                <template v-else>
                  <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 min-w-0 truncate">{{ child.name }}</h4>
                  <div class="flex items-center gap-0.5 shrink-0 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity">
                    <button
                      class="p-1 text-surface-400 hover:text-primary"
                      title="Renommer la sous-catégorie"
                      aria-label="Renommer la sous-catégorie"
                      @click="startRename(child)"
                    >
                      <i class="pi pi-pencil text-xs"></i>
                    </button>
                    <button
                      class="p-1 text-red-500 hover:text-red-700"
                      title="Supprimer la sous-catégorie"
                      aria-label="Supprimer la sous-catégorie"
                      @click="emit('delete-category', child.id)"
                    >
                      <i class="pi pi-trash text-xs"></i>
                    </button>
                  </div>
                  <FinanceTypeTotals
                    :totals="categoryTotals(child.id)"
                    class="text-sm shrink-0 ml-auto"
                  />
                </template>
              </div>
              <EntryList
                :entries="entriesByCategory[child.id] || []"
                :categoryId="child.id"
                :currentMembershipId="currentMembershipId"
                @edit="(entry) => emit('edit-entry', entry)"
                @move="(entryId, toCategoryId) => emit('move-entry', entryId, toCategoryId)"
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
        </div>

        <!-- Pole-level actions -->
        <div class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700">
          <div v-if="editingId === pole.id" class="flex items-center gap-2">
            <InputText
              v-model="editingName"
              class="flex-1 text-sm"
              size="small"
              @keydown.enter="saveRename(pole)"
              @keydown.escape="cancelRename"
            />
            <Button icon="pi pi-check" aria-label="Valider" text rounded size="small" @click="saveRename(pole)" />
            <Button icon="pi pi-times" aria-label="Annuler" text rounded size="small" @click="cancelRename" />
          </div>
          <div v-else class="flex flex-wrap items-center gap-2 sm:gap-3">
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
              class="text-xs sm:text-sm text-surface-500 hover:text-primary hover:underline flex items-center gap-1"
              @click="startRename(pole)"
            >
              <i class="pi pi-pencil text-xs"></i>
              Renommer
            </button>
            <button
              class="text-xs sm:text-sm text-red-500 hover:text-red-700 flex items-center gap-1 ml-auto"
              @click="emit('delete-category', pole.id)"
            >
              <i class="pi pi-trash text-xs"></i>
              Supprimer la catégorie
            </button>
          </div>
        </div>
      </AccordionContent>
    </AccordionPanel>
  </Accordion>
</template>

<script setup>
import Accordion from 'primevue/accordion'
import AccordionContent from 'primevue/accordioncontent'
import AccordionHeader from 'primevue/accordionheader'
import AccordionPanel from 'primevue/accordionpanel'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { financeCategoryTotals } from '../../../utils/financeCategoryTotals.js'
import EntryList from './EntryList.vue'
import FinanceTypeTotals from './FinanceTypeTotals.vue'

const STORAGE_KEY_PREFIX = 'finance_open_panels_'

const props = defineProps({
  poles: { type: Array, required: true },
  entriesByCategory: { type: Object, required: true },
  currentMembershipId: { type: String, default: null }
})

const emit = defineEmits([
  'add-entry',
  'edit-entry',
  'move-entry',
  'add-category',
  'delete-category',
  'rename-category'
])

const editingId = ref(null)
const editingName = ref('')

function startRename(category) {
  editingId.value = category.id
  editingName.value = category.name
}

function cancelRename() {
  editingId.value = null
  editingName.value = ''
}

function saveRename(category) {
  const name = editingName.value.trim()
  if (!name) return
  emit('rename-category', { id: category.id, name })
  cancelRename()
}

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

watch(
  openPanels,
  (value) => {
    localStorage.setItem(storageKey, JSON.stringify(value))
  },
  { deep: true }
)

const poleStats = computed(() => {
  const stats = new Map()
  for (const pole of props.poles) {
    const entries = [...(props.entriesByCategory[pole.id] || [])]
    for (const child of pole.children) {
      entries.push(...(props.entriesByCategory[child.id] || []))
    }
    stats.set(pole.id, { count: entries.length, totals: financeCategoryTotals(entries) })
  }
  return stats
})

const NO_TOTALS = financeCategoryTotals([])

function countEntries(pole) {
  return poleStats.value.get(pole.id)?.count ?? 0
}

/** The pole and its sub-categories together. */
function poleTotals(pole) {
  return poleStats.value.get(pole.id)?.totals ?? NO_TOTALS
}

function categoryTotals(categoryId) {
  return financeCategoryTotals(props.entriesByCategory[categoryId] || [])
}

function progressRows(pole) {
  const { income, expense } = poleTotals(pole)
  return [
    { label: 'Revenus', ...income },
    { label: 'Dépenses', ...expense }
  ].filter((row) => row.total > 0)
}
</script>
