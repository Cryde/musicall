<template>
  <div>
    <div v-if="financeStore.isLoading">
      <!-- Initial load skeleton -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div v-for="i in 4" :key="i" class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700">
          <Skeleton width="60%" height="0.875rem" class="mb-2" />
          <Skeleton width="80%" height="1.5rem" />
        </div>
      </div>
      <div v-for="i in 3" :key="i" class="bg-surface-0 dark:bg-surface-800 rounded-xl p-4 border border-surface-200 dark:border-surface-700 mb-3">
        <div class="flex items-center justify-between">
          <Skeleton width="40%" height="1rem" />
          <Skeleton width="15%" height="0.875rem" />
        </div>
      </div>
    </div>

    <div v-else-if="financeStore.loadError" class="flex flex-col items-center justify-center min-h-[400px] p-8 gap-4">
      <Message severity="error" :closable="false">{{ financeStore.loadError }}</Message>
      <Button label="Réessayer" icon="pi pi-refresh" severity="secondary" @click="handleRetry" />
    </div>

    <FinanceBootstrap
      v-else-if="financeStore.categories.length === 0"
      @bootstrapped="handleBootstrapped"
      @create-manual="handleAddCategory(null)"
    />

    <div v-else>
      <div class="flex items-center justify-between mb-6">
        <DateRangePicker
          v-if="viewMode !== 'chart'"
          :from="financeStore.dateFrom"
          :to="financeStore.dateTo"
          :presets="financePresets"
          @apply="handleDateRangeApply"
        />
        <span v-else class="text-sm text-surface-500 dark:text-surface-400 flex items-center gap-2">
          <i class="pi pi-clock" />
          Toutes périodes
        </span>
        <div class="flex items-center gap-1">
          <Button
            :icon="'pi pi-objects-column'"
            size="small"
            :severity="viewMode === 'categories' ? 'primary' : 'secondary'"
            :outlined="viewMode !== 'categories'"
            :text="viewMode !== 'categories'"
            title="Vue par catégories"
            @click="viewMode = 'categories'"
          />
          <Button
            :icon="'pi pi-list'"
            size="small"
            :severity="viewMode === 'timeline' ? 'primary' : 'secondary'"
            :outlined="viewMode !== 'timeline'"
            :text="viewMode !== 'timeline'"
            title="Vue chronologique"
            @click="viewMode = 'timeline'"
          />
          <Button
            :icon="'pi pi-chart-pie'"
            size="small"
            :severity="viewMode === 'chart' ? 'primary' : 'secondary'"
            :outlined="viewMode !== 'chart'"
            :text="viewMode !== 'chart'"
            title="Vue graphique (toutes périodes)"
            @click="viewMode = 'chart'"
          />
        </div>
      </div>

      <template v-if="viewMode !== 'chart'">
      <FinanceMetricCards :summary="financeStore.summary" class="mb-6" :class="{ 'opacity-50 pointer-events-none': isDateRangeLoading }" />

      <div class="flex flex-col lg:flex-row gap-6" :class="{ 'opacity-50 pointer-events-none': isDateRangeLoading }">
        <div class="flex-1 min-w-0">
          <FinancePoleAccordion
            v-if="viewMode === 'categories'"
            :poles="financeStore.categoryTree"
            :entriesByCategory="financeStore.entriesByCategory"
            :currentMembershipId="financeStore.summary?.current_membership_id"
            @add-entry="handleAddEntry"
            @edit-entry="handleEditEntry"
            @move-entry="handleMoveEntry"
            @add-category="handleAddCategory"
            @delete-category="handleDeleteCategory"
            @rename-category="handleRenameCategory"
          />
          <FinanceTimeline
            v-else
            :entries="financeStore.entriesByDate"
            :categories="financeStore.categories"
            :currentMembershipId="financeStore.summary?.current_membership_id"
            @edit-entry="handleEditEntry"
            @add-entry="handleAddEntry(null)"
            @add-category="handleAddCategory(null)"
          />

          <!-- Mobile: sidebar content below accordion -->
          <div class="lg:hidden mt-6 flex flex-col gap-6">
            <FinanceSidebar :summary="financeStore.summary" />

            <RecurrenceList
              :recurrences="financeStore.recurrences"
              @add="handleAddRecurrence"
              @edit="handleEditRecurrence"
            />
          </div>
        </div>

        <!-- Desktop: sidebar -->
        <div class="hidden lg:block w-80 flex-shrink-0">
          <FinanceSidebar :summary="financeStore.summary" />

          <RecurrenceList
            :recurrences="financeStore.recurrences"
            class="mt-6"
            @add="handleAddRecurrence"
            @edit="handleEditRecurrence"
          />
        </div>
      </div>
      </template>

      <FinancePieChart
        v-else
        :entries="financeStore.allTimeEntries"
        :categoryTree="financeStore.categoryTree"
        :isLoading="financeStore.isLoadingAllTime"
      />
    </div>

    <CreateCategoryDialog
      v-model:visible="showCategoryDialog"
      :parentId="categoryParentId"
      @created="handleCategoryCreated"
    />

    <FinanceDrawer
      v-model:visible="drawerVisible"
      :entry="editingEntry"
      :categoryId="drawerCategoryId"
      :bandSpaceId="bandSpaceId"
      :currentMembershipId="financeStore.summary?.current_membership_id"
      @saved="handleEntrySaved"
      @deleted="handleEntryDeleted"
    />

    <RecurrenceDrawer
      v-model:visible="recurrenceDrawerVisible"
      :recurrence="editingRecurrence"
      :bandSpaceId="bandSpaceId"
      :categories="financeStore.categories"
      @saved="handleRecurrenceSaved"
      @deleted="handleRecurrenceDeleted"
    />

  </div>
</template>

<script setup>
import { trackUmamiEvent } from '@jaseeey/vue-umami-plugin'
import {
  endOfMonth,
  endOfYear,
  format,
  parseISO,
  startOfDay,
  startOfMonth,
  startOfYear,
  subMonths,
  subYears
} from 'date-fns'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Skeleton from 'primevue/skeleton'
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import DateRangePicker from '../../components/Admin/DateRangePicker.vue'
import CreateCategoryDialog from '../../components/BandSpace/Finance/CreateCategoryDialog.vue'
import FinanceBootstrap from '../../components/BandSpace/Finance/FinanceBootstrap.vue'
import FinanceDrawer from '../../components/BandSpace/Finance/FinanceDrawer.vue'
import FinanceMetricCards from '../../components/BandSpace/Finance/FinanceMetricCards.vue'
import FinancePieChart from '../../components/BandSpace/Finance/FinancePieChart.vue'
import FinancePoleAccordion from '../../components/BandSpace/Finance/FinancePoleAccordion.vue'
import FinanceSidebar from '../../components/BandSpace/Finance/FinanceSidebar.vue'
import FinanceTimeline from '../../components/BandSpace/Finance/FinanceTimeline.vue'
import RecurrenceDrawer from '../../components/BandSpace/Finance/RecurrenceDrawer.vue'
import RecurrenceList from '../../components/BandSpace/Finance/RecurrenceList.vue'
import { useBandSpaceFinanceStore } from '../../store/bandSpace/bandSpaceFinance.js'

const route = useRoute()
const router = useRouter()
const confirm = useConfirm()
const toast = useToast()
const financeStore = useBandSpaceFinanceStore()
// Wipe any previous space's categories/entries/summary synchronously before
// first render so switching from /band/A/finances to /band/B/finances doesn't
// flash A's numbers. The :key on <router-view> remounts this view but Pinia
// keeps A's data until cleared. clear() preserves dateFrom/dateTo so the
// restoreDateRange() call below still applies.
financeStore.clear()

const today = startOfDay(new Date())

function quarterStart(date, offset = 0) {
  const q = Math.floor(date.getMonth() / 3) + offset
  const year = date.getFullYear() + Math.floor(q / 4)
  const month = (((q % 4) + 4) % 4) * 3
  return startOfMonth(new Date(year, month, 1))
}

function quarterEnd(date, offset = 0) {
  const q = Math.floor(date.getMonth() / 3) + offset
  const year = date.getFullYear() + Math.floor(q / 4)
  const month = (((q % 4) + 4) % 4) * 3 + 2
  return endOfMonth(new Date(year, month, 1))
}

const financePresets = computed(() => {
  const summary = financeStore.summary
  const minDate = summary?.min_date ? parseISO(summary.min_date) : startOfYear(today)
  const maxDate = summary?.max_date ? parseISO(summary.max_date) : endOfYear(today)

  return [
    {
      key: 'this_month',
      label: 'Ce mois',
      from: () => startOfMonth(today),
      to: () => endOfMonth(today)
    },
    {
      key: 'last_month',
      label: 'Mois dernier',
      from: () => startOfMonth(subMonths(today, 1)),
      to: () => endOfMonth(subMonths(today, 1))
    },
    {
      key: 'this_quarter',
      label: 'Ce trimestre',
      from: () => quarterStart(today),
      to: () => quarterEnd(today)
    },
    {
      key: 'last_quarter',
      label: 'Trimestre dernier',
      from: () => quarterStart(today, -1),
      to: () => quarterEnd(today, -1)
    },
    {
      key: 'this_year',
      label: 'Cette année',
      from: () => startOfYear(today),
      to: () => endOfYear(today)
    },
    {
      key: 'last_year',
      label: 'Année dernière',
      from: () => startOfYear(subYears(today, 1)),
      to: () => endOfYear(subYears(today, 1))
    },
    {
      key: 'all',
      label: 'Depuis le début',
      from: () => minDate,
      to: () => (maxDate > endOfYear(today) ? maxDate : endOfYear(today))
    }
  ]
})

const bandSpaceId = route.params.id
const dateRangeStorageKey = `finance_date_range_${bandSpaceId}`

function restoreDateRange() {
  try {
    const stored = localStorage.getItem(dateRangeStorageKey)
    if (stored) {
      const { from, to } = JSON.parse(stored)
      financeStore.setDateRange(parseISO(from), parseISO(to))
    }
  } catch {
    // ignore corrupt data
  }
}

restoreDateRange()

watch(
  () => [financeStore.dateFrom, financeStore.dateTo],
  ([from, to]) => {
    localStorage.setItem(
      dateRangeStorageKey,
      JSON.stringify({
        from: format(from, 'yyyy-MM-dd'),
        to: format(to, 'yyyy-MM-dd')
      })
    )
  }
)

const viewModeStorageKey = `finance_view_mode_${bandSpaceId}`
const viewMode = ref(localStorage.getItem(viewModeStorageKey) || 'categories')

watch(viewMode, (value) => {
  localStorage.setItem(viewModeStorageKey, value)
  // Refetch on each switch to chart (no cache) so it reflects edits made in the other views -
  // matches how loadEntries/loadSummary behave.
  if (value === 'chart') {
    financeStore.loadAllTimeEntries(bandSpaceId)
  }
})

const drawerVisible = ref(false)
const editingEntry = ref(null)
const drawerCategoryId = ref(null)
const showCategoryDialog = ref(false)
const categoryParentId = ref(null)
const recurrenceDrawerVisible = ref(false)
const editingRecurrence = ref(null)
const isDateRangeLoading = ref(false)

onMounted(() => {
  financeStore.loadCategories(bandSpaceId)
  financeStore.loadEntries(bandSpaceId)
  financeStore.loadSummary(bandSpaceId)
  financeStore.loadRecurrences(bandSpaceId)
  if (viewMode.value === 'chart') {
    financeStore.loadAllTimeEntries(bandSpaceId)
  }
})

watch(
  () => route.query.entry,
  async (entryId) => {
    if (!entryId) {
      editingEntry.value = null
      drawerVisible.value = false
      financeStore.setActiveEntry(null)
      return
    }
    try {
      await financeStore.setActiveEntry(entryId, bandSpaceId)
      if (financeStore.activeEntry) {
        editingEntry.value = financeStore.activeEntry
        drawerCategoryId.value = financeStore.activeEntry.category_id
        drawerVisible.value = true
      } else {
        toast.add({ severity: 'error', summary: 'Entrée introuvable', life: 4000 })
        router.replace({ query: { ...route.query, entry: undefined } })
      }
    } catch {
      toast.add({ severity: 'error', summary: 'Erreur de chargement', life: 4000 })
      router.replace({ query: { ...route.query, entry: undefined } })
    }
  },
  { immediate: true }
)

watch(drawerVisible, (val) => {
  if (!val && route.query.entry) {
    router.replace({ query: { ...route.query, entry: undefined } })
  }
})

onUnmounted(() => {
  financeStore.clear()
})

function handleRetry() {
  financeStore.loadCategories(bandSpaceId)
  financeStore.loadEntries(bandSpaceId)
  financeStore.loadSummary(bandSpaceId)
}

async function handleDateRangeApply({ from, to }) {
  financeStore.setDateRange(from, to)
  isDateRangeLoading.value = true
  try {
    await Promise.all([
      financeStore.loadEntries(bandSpaceId),
      financeStore.loadSummary(bandSpaceId)
    ])
  } finally {
    isDateRangeLoading.value = false
  }
}

function handleBootstrapped() {
  trackUmamiEvent('finance-bootstrap')
  financeStore.loadEntries(bandSpaceId)
  financeStore.loadSummary(bandSpaceId)
}

function handleAddEntry(categoryId) {
  editingEntry.value = null
  drawerCategoryId.value = categoryId
  drawerVisible.value = true
}

function handleEditEntry(entry) {
  editingEntry.value = entry
  drawerCategoryId.value = entry.category_id
  drawerVisible.value = true
}

async function handleMoveEntry(entryId, toCategoryId) {
  try {
    await financeStore.moveEntryToCategory(bandSpaceId, entryId, toCategoryId)
    trackUmamiEvent('finance-entry-move')
  } catch {
    // moveEntryToCategory already rolled the optimistic move back in the store.
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de déplacer cette entrée',
      life: 5000
    })
  }
}

function handleAddCategory(parentId = null) {
  categoryParentId.value = parentId
  showCategoryDialog.value = true
}

async function handleCategoryCreated({ name, parentId }) {
  try {
    const data = { name }
    if (parentId) {
      data.parent_id = parentId
    }
    await financeStore.createCategory(bandSpaceId, data)
    trackUmamiEvent('finance-category-create', { has_parent: !!parentId })
    toast.add({ severity: 'success', summary: 'Catégorie créée', life: 3000 })
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de créer la catégorie',
      life: 5000
    })
  }
}

function handleDeleteCategory(categoryId) {
  confirm.require({
    message:
      'Es-tu sûr de vouloir supprimer cette catégorie ? Les entrées et récurrences associées seront également supprimées.',
    header: 'Confirmer la suppression',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Annuler',
    acceptLabel: 'Supprimer',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await financeStore.deleteCategory(bandSpaceId, categoryId)
        trackUmamiEvent('finance-category-delete')
        toast.add({ severity: 'success', summary: 'Catégorie supprimée', life: 3000 })
      } catch {
        toast.add({
          severity: 'error',
          summary: 'Erreur',
          detail: 'Impossible de supprimer la catégorie',
          life: 5000
        })
      }
    }
  })
}

async function handleRenameCategory({ id, name }) {
  try {
    await financeStore.updateCategory(bandSpaceId, id, { name })
    trackUmamiEvent('finance-category-rename')
    toast.add({ severity: 'success', summary: 'Catégorie renommée', life: 3000 })
  } catch {
    toast.add({
      severity: 'error',
      summary: 'Erreur',
      detail: 'Impossible de renommer la catégorie',
      life: 5000
    })
  }
}

function handleAddRecurrence() {
  editingRecurrence.value = null
  recurrenceDrawerVisible.value = true
}

function handleEditRecurrence(recurrence) {
  editingRecurrence.value = recurrence
  recurrenceDrawerVisible.value = true
}

function handleRecurrenceSaved() {
  trackUmamiEvent(
    editingRecurrence.value ? 'finance-recurrence-update' : 'finance-recurrence-create'
  )
  recurrenceDrawerVisible.value = false
  editingRecurrence.value = null
  toast.add({ severity: 'success', summary: 'Récurrence enregistrée', life: 3000 })
}

function handleRecurrenceDeleted() {
  trackUmamiEvent('finance-recurrence-delete')
  recurrenceDrawerVisible.value = false
  editingRecurrence.value = null
  toast.add({ severity: 'success', summary: 'Récurrence supprimée', life: 3000 })
}

function handleEntrySaved() {
  trackUmamiEvent(editingEntry.value ? 'finance-entry-update' : 'finance-entry-create')
  drawerVisible.value = false
  editingEntry.value = null
  toast.add({ severity: 'success', summary: 'Entrée enregistrée', life: 3000 })
}

function handleEntryDeleted() {
  trackUmamiEvent('finance-entry-delete')
  drawerVisible.value = false
  editingEntry.value = null
  toast.add({ severity: 'success', summary: 'Entrée supprimée', life: 3000 })
}
</script>
