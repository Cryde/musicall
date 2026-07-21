import { endOfMonth, endOfYear, format, parseISO, startOfMonth, startOfYear } from 'date-fns'
import { defineStore } from 'pinia'
import { computed, readonly, ref } from 'vue'
import bandSpaceFinanceApi from '../../api/bandSpace/band-space-finance.js'

function formatDate(date) {
  return format(date, 'yyyy-MM-dd')
}

export const useBandSpaceFinanceStore = defineStore('bandSpaceFinance', () => {
  const categories = ref([])
  const entries = ref([])
  const summary = ref(null)
  const isLoading = ref(false)
  const isLoadingEntries = ref(false)
  const isLoadingSummary = ref(false)
  const isCreating = ref(false)
  const isSaving = ref(false)
  const isDeleting = ref(false)
  const isBootstrapping = ref(false)
  const loadError = ref(null)
  const recurrences = ref([])
  const dateFrom = ref(startOfYear(new Date()))
  const dateTo = ref(endOfYear(new Date()))
  const activeEntry = ref(null)
  // All-time entries for the (non-time-filtered) chart view, loaded lazily. The per-category
  // pie and the top-10 lists are both derived from these client-side.
  const allTimeEntries = ref([])
  const isLoadingAllTime = ref(false)
  let entriesRequestId = 0
  let summaryRequestId = 0
  let categoriesRequestId = 0
  let allTimeRequestId = 0

  const categoryTree = computed(() => buildTree(categories.value))

  const entriesByDate = computed(() =>
    [...entries.value].sort((a, b) => b.date.localeCompare(a.date))
  )

  const entriesByCategory = computed(() => {
    const map = {}
    for (const entry of entries.value) {
      const catId = entry.category_id
      if (!map[catId]) {
        map[catId] = []
      }
      map[catId].push(entry)
    }
    return map
  })

  function buildTree(flatList) {
    const map = new Map()
    const roots = []

    for (const cat of flatList) {
      map.set(cat.id, { ...cat, children: [] })
    }

    for (const cat of flatList) {
      const node = map.get(cat.id)
      if (cat.parent_id && map.has(cat.parent_id)) {
        map.get(cat.parent_id).children.push(node)
      } else {
        roots.push(node)
      }
    }

    return roots
  }

  function dateParams() {
    return {
      from: formatDate(dateFrom.value),
      to: formatDate(dateTo.value)
    }
  }

  async function loadCategories(bandSpaceId) {
    const requestId = ++categoriesRequestId
    const isInitialLoad = categories.value.length === 0
    if (isInitialLoad) {
      isLoading.value = true
    }
    loadError.value = null
    try {
      const data = await bandSpaceFinanceApi.getCategories(bandSpaceId)
      if (requestId !== categoriesRequestId) return
      categories.value = data
    } catch {
      if (requestId !== categoriesRequestId) return
      categories.value = []
      loadError.value = 'Impossible de charger les catégories'
    } finally {
      if (isInitialLoad && requestId === categoriesRequestId) {
        isLoading.value = false
      }
    }
  }

  async function loadEntries(bandSpaceId) {
    const requestId = ++entriesRequestId
    isLoadingEntries.value = true
    const { from, to } = dateParams()
    try {
      const result = await bandSpaceFinanceApi.getEntries(bandSpaceId, from, to)
      if (requestId === entriesRequestId) {
        entries.value = result
      }
    } catch {
      if (requestId === entriesRequestId) {
        entries.value = []
      }
    } finally {
      if (requestId === entriesRequestId) {
        isLoadingEntries.value = false
      }
    }
  }

  async function loadSummary(bandSpaceId) {
    const requestId = ++summaryRequestId
    isLoadingSummary.value = true
    const { from, to } = dateParams()
    try {
      const result = await bandSpaceFinanceApi.getSummary(bandSpaceId, from, to)
      if (requestId === summaryRequestId) {
        summary.value = result
      }
    } catch {
      if (requestId === summaryRequestId) {
        summary.value = null
      }
    } finally {
      if (requestId === summaryRequestId) {
        isLoadingSummary.value = false
      }
    }
  }

  // Loaded on demand when the chart view opens; ignores the page date range (all-time).
  async function loadAllTimeEntries(bandSpaceId) {
    const requestId = ++allTimeRequestId
    isLoadingAllTime.value = true
    try {
      const result = await bandSpaceFinanceApi.getEntries(bandSpaceId)
      if (requestId === allTimeRequestId) {
        allTimeEntries.value = result ?? []
      }
    } catch {
      if (requestId === allTimeRequestId) {
        allTimeEntries.value = []
      }
    } finally {
      if (requestId === allTimeRequestId) {
        isLoadingAllTime.value = false
      }
    }
  }

  function setDateRange(from, to) {
    dateFrom.value = from
    dateTo.value = to
  }

  function expandDateRangeIfNeeded(entryDate) {
    if (!entryDate) return
    const date = typeof entryDate === 'string' ? parseISO(entryDate) : entryDate
    if (date < dateFrom.value) {
      dateFrom.value = startOfMonth(date)
    }
    if (date > dateTo.value) {
      dateTo.value = endOfMonth(date)
    }
  }

  async function createCategory(bandSpaceId, data) {
    isCreating.value = true
    try {
      const newCat = await bandSpaceFinanceApi.createCategory(bandSpaceId, data)
      await loadCategories(bandSpaceId)
      return newCat
    } finally {
      isCreating.value = false
    }
  }

  async function updateCategory(bandSpaceId, categoryId, data) {
    isSaving.value = true
    try {
      await bandSpaceFinanceApi.updateCategory(bandSpaceId, categoryId, data)
      await loadCategories(bandSpaceId)
    } finally {
      isSaving.value = false
    }
  }

  async function deleteCategory(bandSpaceId, categoryId) {
    isDeleting.value = true
    try {
      await bandSpaceFinanceApi.deleteCategory(bandSpaceId, categoryId)
      await loadCategories(bandSpaceId)
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
    } finally {
      isDeleting.value = false
    }
  }

  async function createEntry(bandSpaceId, data) {
    isCreating.value = true
    try {
      const newEntry = await bandSpaceFinanceApi.createEntry(bandSpaceId, data)
      expandDateRangeIfNeeded(data.date)
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
      return newEntry
    } finally {
      isCreating.value = false
    }
  }

  async function updateEntry(bandSpaceId, entryId, data) {
    isSaving.value = true
    try {
      const updated = await bandSpaceFinanceApi.updateEntry(bandSpaceId, entryId, data)
      expandDateRangeIfNeeded(data.date)
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
      return updated
    } finally {
      isSaving.value = false
    }
  }

  // Move an entry to another category (drag-and-drop). Updates entries.value optimistically so the
  // accordion header totals and the row lists stay in sync during the round-trip, then persists via
  // updateEntry (which reloads the authoritative state). Rolls back on failure. Mirrors
  // bandSpaceTasks.moveTaskToColumn.
  async function moveEntryToCategory(bandSpaceId, entryId, categoryId) {
    const snapshot = [...entries.value]
    entries.value = entries.value.map((entry) =>
      entry.id === entryId ? { ...entry, category_id: categoryId } : entry
    )
    try {
      return await updateEntry(bandSpaceId, entryId, { category_id: categoryId })
    } catch (error) {
      entries.value = snapshot
      throw error
    }
  }

  async function deleteEntry(bandSpaceId, entryId) {
    isDeleting.value = true
    try {
      await bandSpaceFinanceApi.deleteEntry(bandSpaceId, entryId)
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
    } finally {
      isDeleting.value = false
    }
  }

  async function loadRecurrences(bandSpaceId) {
    try {
      recurrences.value = await bandSpaceFinanceApi.getRecurrences(bandSpaceId)
    } catch {
      recurrences.value = []
    }
  }

  async function createRecurrence(bandSpaceId, data) {
    isCreating.value = true
    try {
      const result = await bandSpaceFinanceApi.createRecurrence(bandSpaceId, data)
      await loadRecurrences(bandSpaceId)
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
      return result
    } finally {
      isCreating.value = false
    }
  }

  async function updateRecurrence(bandSpaceId, recurrenceId, data) {
    isSaving.value = true
    try {
      await bandSpaceFinanceApi.updateRecurrence(bandSpaceId, recurrenceId, data)
      await loadRecurrences(bandSpaceId)
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
    } finally {
      isSaving.value = false
    }
  }

  async function deleteRecurrence(bandSpaceId, recurrenceId) {
    isDeleting.value = true
    try {
      await bandSpaceFinanceApi.deleteRecurrence(bandSpaceId, recurrenceId)
      await loadRecurrences(bandSpaceId)
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
    } finally {
      isDeleting.value = false
    }
  }

  async function bootstrap(bandSpaceId) {
    isBootstrapping.value = true
    try {
      await bandSpaceFinanceApi.bootstrap(bandSpaceId)
      await loadCategories(bandSpaceId)
    } finally {
      isBootstrapping.value = false
    }
  }

  async function setActiveEntry(entryId, bandSpaceId = null) {
    if (!entryId) {
      activeEntry.value = null
      return
    }
    const fromList = entries.value.find((e) => String(e.id) === String(entryId))
    if (fromList) {
      activeEntry.value = fromList
      return
    }
    if (!bandSpaceId) {
      activeEntry.value = null
      return
    }
    activeEntry.value = await bandSpaceFinanceApi.getEntry(bandSpaceId, entryId)
  }

  function clear() {
    categories.value = []
    entries.value = []
    recurrences.value = []
    summary.value = null
    loadError.value = null
    activeEntry.value = null
    allTimeEntries.value = []
  }

  return {
    categories: readonly(categories),
    entries: readonly(entries),
    summary: readonly(summary),
    isLoading: readonly(isLoading),
    isLoadingEntries: readonly(isLoadingEntries),
    isLoadingSummary: readonly(isLoadingSummary),
    isCreating: readonly(isCreating),
    isSaving: readonly(isSaving),
    isDeleting: readonly(isDeleting),
    isBootstrapping: readonly(isBootstrapping),
    loadError: readonly(loadError),
    recurrences: readonly(recurrences),
    dateFrom: readonly(dateFrom),
    dateTo: readonly(dateTo),
    activeEntry: readonly(activeEntry),
    allTimeEntries: readonly(allTimeEntries),
    isLoadingAllTime: readonly(isLoadingAllTime),
    categoryTree,
    entriesByDate,
    entriesByCategory,
    loadCategories,
    loadEntries,
    loadSummary,
    loadAllTimeEntries,
    loadRecurrences,
    setDateRange,
    createCategory,
    updateCategory,
    deleteCategory,
    createEntry,
    updateEntry,
    moveEntryToCategory,
    deleteEntry,
    createRecurrence,
    updateRecurrence,
    deleteRecurrence,
    bootstrap,
    setActiveEntry,
    clear
  }
})
