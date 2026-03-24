import { endOfYear, format, startOfYear } from 'date-fns'
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
  let entriesRequestId = 0
  let summaryRequestId = 0

  const categoryTree = computed(() => buildTree(categories.value))

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
    const isInitialLoad = categories.value.length === 0
    if (isInitialLoad) {
      isLoading.value = true
    }
    loadError.value = null
    try {
      categories.value = await bandSpaceFinanceApi.getCategories(bandSpaceId)
    } catch {
      categories.value = []
      loadError.value = 'Impossible de charger les catégories'
    } finally {
      if (isInitialLoad) {
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

  function setDateRange(from, to) {
    dateFrom.value = from
    dateTo.value = to
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
      await loadEntries(bandSpaceId)
      await loadSummary(bandSpaceId)
      return updated
    } finally {
      isSaving.value = false
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

  function clear() {
    categories.value = []
    entries.value = []
    recurrences.value = []
    summary.value = null
    loadError.value = null
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
    categoryTree,
    entriesByCategory,
    loadCategories,
    loadEntries,
    loadSummary,
    loadRecurrences,
    setDateRange,
    createCategory,
    updateCategory,
    deleteCategory,
    createEntry,
    updateEntry,
    deleteEntry,
    createRecurrence,
    updateRecurrence,
    deleteRecurrence,
    bootstrap,
    clear
  }
})
