<template>
  <div v-if="isBelowMd" class="mb-2 flex items-center justify-between gap-2">
    <h2 class="font-semibold">{{ t('band_space_finance.phone.categories') }}</h2>
    <Button
      icon="pi pi-plus"
      :label="t('band_space_finance.common.add_category')"
      size="small"
      text
      @click="emit('add-category', null)"
    />
  </div>
  <div v-else class="flex justify-end mb-4">
    <Button
      icon="pi pi-plus"
      :label="t('band_space_finance.common.add_category')"
      size="small"
      outlined
      @click="emit('add-category', null)"
    />
  </div>
  <Accordion v-model:value="openPanels" multiple>
    <AccordionPanel v-for="pole in poles" :key="pole.id" :value="pole.id">
      <AccordionHeader>
        <div class="flex flex-wrap items-center justify-between w-full min-w-0 pr-2 gap-x-3 gap-y-1 max-md:flex-col max-md:items-start max-md:gap-y-0.5">
          <span class="font-semibold min-w-0 max-w-full truncate">{{ pole.name }}</span>
          <span class="flex items-baseline gap-2 sm:gap-3 shrink-0">
            <FinanceTypeTotals :totals="poleTotals(pole)" class="text-sm font-semibold" />
            <span class="text-xs text-surface-500 dark:text-surface-400">{{ t('band_space_finance.common.entry_count', countEntries(pole)) }}</span>
          </span>
        </div>
      </AccordionHeader>
      <!-- PrimeVue's content is a grid whose wrapper may grow to its widest row: without the
           min-w-0 an entry's label pushed the page wider than a phone. -->
      <AccordionContent :pt="{ contentWrapper: { class: 'min-w-0' } }">
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
            <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">{{ t('band_space_finance.pole_accordion.paid_share', { type: row.label, percent: row.percent }) }}</p>
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
                    :id="renameInputId(child)"
                    v-model="editingName"
                    class="flex-1 text-sm"
                    size="small"
                    @keydown.enter="saveRename(child)"
                    @keydown.escape="cancelRename"
                  />
                  <Button icon="pi pi-check" :aria-label="t('band_space_finance.pole_accordion.validate')" text rounded size="small" @click="saveRename(child)" />
                  <Button icon="pi pi-times" :aria-label="t('band_space_finance.common.cancel')" text rounded size="small" @click="cancelRename" />
                </template>
                <template v-else>
                  <h4 class="text-sm font-medium text-surface-700 dark:text-surface-300 min-w-0 truncate">{{ child.name }}</h4>
                  <div class="flex items-center gap-0.5 shrink-0 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity">
                    <button
                      class="p-1 text-surface-400 hover:text-primary max-md:flex max-md:h-11 max-md:w-11 max-md:items-center max-md:justify-center"
                      :title="t('band_space_finance.pole_accordion.rename_child')"
                      :aria-label="t('band_space_finance.pole_accordion.rename_child')"
                      @click="startRename(child)"
                    >
                      <i class="pi pi-pencil text-xs"></i>
                    </button>
                    <button
                      class="p-1 text-red-500 hover:text-red-700 max-md:flex max-md:h-11 max-md:w-11 max-md:items-center max-md:justify-center"
                      :title="t('band_space_finance.pole_accordion.delete_child')"
                      :aria-label="t('band_space_finance.pole_accordion.delete_child')"
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
                {{ t('band_space_finance.common.add_entry') }}
              </button>
            </div>
          </div>
        </div>

        <!-- Pole-level actions -->
        <div class="mt-3 pt-3 border-t border-surface-200 dark:border-surface-700">
          <div v-if="editingId === pole.id" class="flex items-center gap-2">
            <InputText
              :id="renameInputId(pole)"
              v-model="editingName"
              class="flex-1 text-sm"
              size="small"
              @keydown.enter="saveRename(pole)"
              @keydown.escape="cancelRename"
            />
            <Button icon="pi pi-check" :aria-label="t('band_space_finance.pole_accordion.validate')" text rounded size="small" @click="saveRename(pole)" />
            <Button icon="pi pi-times" :aria-label="t('band_space_finance.common.cancel')" text rounded size="small" @click="cancelRename" />
          </div>
          <!-- A phone: the entry first, the rest of the category behind its ⋮ -->
          <div v-else-if="isBelowMd" class="flex items-center gap-2">
            <Button
              icon="pi pi-plus"
              :label="t('band_space_finance.common.add_entry')"
              severity="secondary"
              outlined
              class="flex-1"
              @click="emit('add-entry', pole.id)"
            />
            <Button
              icon="pi pi-ellipsis-v"
              severity="secondary"
              text
              rounded
              :aria-label="t('band_space_finance.phone.category_actions', { name: pole.name })"
              aria-haspopup="dialog"
              @click="openCategorySheet(pole)"
            />
          </div>
          <div v-else class="flex flex-wrap items-center gap-2 sm:gap-3">
            <button
              class="text-xs sm:text-sm text-primary hover:underline flex items-center gap-1"
              @click="emit('add-entry', pole.id)"
            >
              <i class="pi pi-plus text-xs"></i>
              {{ t('band_space_finance.common.add_entry') }}
            </button>
            <button
              class="text-xs sm:text-sm text-surface-500 hover:text-primary hover:underline flex items-center gap-1"
              @click="emit('add-category', pole.id)"
            >
              <i class="pi pi-plus text-xs"></i>
              {{ t('band_space_finance.pole_accordion.add_child') }}
            </button>
            <button
              class="text-xs sm:text-sm text-surface-500 hover:text-primary hover:underline flex items-center gap-1"
              @click="startRename(pole)"
            >
              <i class="pi pi-pencil text-xs"></i>
              {{ t('band_space_finance.pole_accordion.rename') }}
            </button>
            <button
              class="text-xs sm:text-sm text-red-500 hover:text-red-700 flex items-center gap-1 ml-auto"
              @click="emit('delete-category', pole.id)"
            >
              <i class="pi pi-trash text-xs"></i>
              {{ t('band_space_finance.pole_accordion.delete_category') }}
            </button>
          </div>
        </div>
      </AccordionContent>
    </AccordionPanel>
  </Accordion>

  <BottomSheet
    v-if="isBelowMd"
    v-model:visible="isCategorySheetOpen"
    :title="categorySheetTarget?.name ?? ''"
    @after-hide="onCategorySheetHidden"
  >
    <SheetAction icon="pi-plus" @click="runCategoryAction((pole) => emit('add-category', pole.id))">
      {{ t('band_space_finance.pole_accordion.add_child') }}
    </SheetAction>
    <SheetAction icon="pi-pencil" @click="runCategoryAction(startRenameAndFocus)">
      {{ t('band_space_finance.pole_accordion.rename') }}
    </SheetAction>
    <SheetAction icon="pi-trash" danger @click="runCategoryAction((pole) => emit('delete-category', pole.id))">
      {{ t('band_space_finance.pole_accordion.delete_category') }}
    </SheetAction>
  </BottomSheet>
</template>

<script setup>
import Accordion from 'primevue/accordion'
import AccordionContent from 'primevue/accordioncontent'
import AccordionHeader from 'primevue/accordionheader'
import AccordionPanel from 'primevue/accordionpanel'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import { computed, nextTick, ref, useId, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute } from 'vue-router'
import { useActionAfterSheetHide } from '../../../composables/useActionAfterSheetHide.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { financeCategoryTotals } from '../../../utils/financeCategoryTotals.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'
import EntryList from './EntryList.vue'
import FinanceTypeTotals from './FinanceTypeTotals.vue'

const STORAGE_KEY_PREFIX = 'finance_open_panels_'

const { t } = useI18n()

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

const isBelowMd = useIsBelowMd()
const uid = useId()
const editingId = ref(null)
const editingName = ref('')

function renameInputId(category) {
  return `${uid}-rename-${category.id}`
}

function startRename(category) {
  editingId.value = category.id
  editingName.value = category.name
}

function cancelRename() {
  editingId.value = null
  editingName.value = ''
}

// From the sheet the field appears where the ⋮ was, so it takes the focus the sheet hands back.
async function startRenameAndFocus(category) {
  startRename(category)
  await nextTick()
  document.getElementById(renameInputId(category))?.focus()
}

const isCategorySheetOpen = ref(false)
const categorySheetTarget = ref(null)
const categorySheetAction = useActionAfterSheetHide(isCategorySheetOpen)
const onCategorySheetHidden = categorySheetAction.onAfterHide

function openCategorySheet(pole) {
  categorySheetTarget.value = pole
  isCategorySheetOpen.value = true
}

// Given the category the sheet was opened for.
function runCategoryAction(action) {
  const pole = categorySheetTarget.value
  categorySheetAction.run(() => action(pole))
}

watch(isBelowMd, () => {
  isCategorySheetOpen.value = false
})

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
    { label: t('band_space_finance.totals.income'), ...income },
    { label: t('band_space_finance.totals.expense'), ...expense }
  ].filter((row) => row.total > 0)
}
</script>
