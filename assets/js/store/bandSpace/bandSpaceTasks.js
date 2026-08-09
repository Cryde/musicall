import { defineStore } from 'pinia'
import { computed, reactive, readonly, ref } from 'vue'
import bandSpaceSettingsApi from '../../api/bandSpace/band-space-settings.js'
import bandSpaceTasksApi from '../../api/bandSpace/band-space-tasks.js'
import { orderColumnAfterDrag, toPositions } from '../../utils/taskOrdering.js'
import { useUserSecurityStore } from '../user/security.js'

export const useBandTasksStore = defineStore('bandTasks', () => {
  const tasks = ref([])
  const archivedTasks = ref([])
  const categories = ref([])
  const members = ref([])
  const activeTaskId = ref(null)
  const isSelectionMode = ref(false)
  const selectedTaskIds = ref(new Set())
  const filters = reactive({
    categoryId: null,
    assigneeId: null,
    unassigned: false,
    priority: null,
    myTasks: false,
    showArchived: false,
    query: '',
    dueDateFrom: null,
    dueDateTo: null,
    overdue: false
  })

  const isLoading = ref(false)
  const isCreating = ref(false)
  const isSaving = ref(false)
  const isDeleting = ref(false)
  const loadError = ref(null)
  const isLoadingActiveTask = ref(false)
  const activeTaskError = ref(null)

  let tasksRequestId = 0
  let activeTaskRequestId = 0

  const tasksByStatus = computed(() => {
    const userSecurityStore = useUserSecurityStore()
    const currentUserId = userSecurityStore.userProfile?.id

    const filtered = tasks.value.filter((task) => {
      if (task.archive_datetime) return false
      if (filters.categoryId && task.category_id !== filters.categoryId) return false
      if (filters.assigneeId && !task.assignees.some((a) => a.id === filters.assigneeId))
        return false
      // Work nobody is on. A member leaving the band comes off their tasks, which can leave some
      // with an empty avatar row, and this is how the rest of the band finds them again.
      if (filters.unassigned && task.assignees.length > 0) return false
      if (filters.priority && task.priority !== filters.priority) return false
      if (filters.myTasks && !task.assignees.some((a) => a.id === currentUserId)) return false
      return true
    })

    const sortByPosition = (a, b) => a.position - b.position

    return {
      todo: filtered.filter((t) => t.status === 'todo').sort(sortByPosition),
      in_progress: filtered.filter((t) => t.status === 'in_progress').sort(sortByPosition),
      done: filtered.filter((t) => t.status === 'done').sort(sortByPosition)
    }
  })

  /**
   * The query, the due-date range and "En retard" are applied by the server: fetchTasks replaces
   * tasks.value with the matching subset, so the rest of a column is simply not in memory and no
   * ordering covering it can be built. Positions are absolute, so writing one anyway would give
   * the visible tasks numbers the hidden ones already hold and scramble the board for every
   * member. Reordering is therefore refused, and the columns are not draggable, while any of
   * those filters is on. The other filters hide tasks the store still holds, so they are fine.
   */
  const isReorderDisabled = computed(
    () =>
      filters.query.trim() !== '' ||
      Boolean(filters.dueDateFrom) ||
      Boolean(filters.dueDateTo) ||
      filters.overdue === true
  )

  /** Every task of a column, hidden ones included, in board order. */
  function columnOrderedIds(status) {
    return tasks.value
      .filter((t) => t.status === status && !t.archive_datetime)
      .sort((a, b) => a.position - b.position)
      .map((t) => t.id)
  }

  const activeTask = computed(() => {
    if (!activeTaskId.value) return null
    return (
      tasks.value.find((t) => t.id === activeTaskId.value) ||
      archivedTasks.value.find((t) => t.id === activeTaskId.value) ||
      null
    )
  })

  async function fetchTasks(bandSpaceId) {
    const requestId = ++tasksRequestId
    isLoading.value = tasks.value.length === 0
    loadError.value = null
    try {
      const trimmedQuery = filters.query.trim()
      const params = {}
      if (trimmedQuery) params.query = trimmedQuery
      if (filters.dueDateFrom) params.dueDateFrom = filters.dueDateFrom
      if (filters.dueDateTo) params.dueDateTo = filters.dueDateTo
      if (filters.overdue) params.overdue = true
      const result = await bandSpaceTasksApi.getTasks(bandSpaceId, params)
      if (requestId === tasksRequestId) {
        tasks.value = result
      }
    } catch (e) {
      if (requestId === tasksRequestId) {
        loadError.value = e.message
      }
    } finally {
      if (requestId === tasksRequestId) {
        isLoading.value = false
      }
    }
  }

  async function fetchTaskById(bandSpaceId, taskId) {
    const requestId = ++activeTaskRequestId
    isLoadingActiveTask.value = true
    activeTaskError.value = null
    try {
      const fetched = await bandSpaceTasksApi.getTask(bandSpaceId, taskId)
      if (requestId !== activeTaskRequestId) return
      const target = fetched.archive_datetime ? archivedTasks : tasks
      const existing = target.value.findIndex((t) => t.id === fetched.id)
      if (existing === -1) {
        target.value = [fetched, ...target.value]
      } else {
        target.value = target.value.map((t) => (t.id === fetched.id ? fetched : t))
      }
    } catch (e) {
      if (requestId !== activeTaskRequestId) return
      activeTaskError.value = e.status === 404 ? 'Tâche introuvable' : e.message
    } finally {
      if (requestId === activeTaskRequestId) {
        isLoadingActiveTask.value = false
      }
    }
  }

  async function fetchArchivedTasks(bandSpaceId) {
    try {
      archivedTasks.value = await bandSpaceTasksApi.getTasks(bandSpaceId, { archived: true })
    } catch {
      // silently fail
    }
  }

  async function fetchCategories(bandSpaceId) {
    try {
      categories.value = await bandSpaceTasksApi.getCategories(bandSpaceId)
    } catch {
      // silently fail, categories are optional
    }
  }

  async function fetchMembers(bandSpaceId) {
    try {
      members.value = await bandSpaceSettingsApi.getMembers(bandSpaceId)
    } catch {
      // silently fail
    }
  }

  async function createTask(bandSpaceId, data) {
    isCreating.value = true
    try {
      const created = await bandSpaceTasksApi.createTask(bandSpaceId, data)
      tasks.value = [created, ...tasks.value]
      return created
    } finally {
      isCreating.value = false
    }
  }

  async function createComment(bandSpaceId, taskId, data) {
    const created = await bandSpaceTasksApi.createComment(bandSpaceId, taskId, data)
    const bump = (t) => (t.id === taskId ? { ...t, comment_count: (t.comment_count ?? 0) + 1 } : t)
    tasks.value = tasks.value.map(bump)
    archivedTasks.value = archivedTasks.value.map(bump)
    return created
  }

  async function updateComment(bandSpaceId, taskId, commentId, data) {
    return await bandSpaceTasksApi.updateComment(bandSpaceId, taskId, commentId, data)
  }

  async function deleteComment(bandSpaceId, taskId, commentId) {
    await bandSpaceTasksApi.deleteComment(bandSpaceId, taskId, commentId)
    const decrement = (t) =>
      t.id === taskId ? { ...t, comment_count: Math.max(0, (t.comment_count ?? 0) - 1) } : t
    tasks.value = tasks.value.map(decrement)
    archivedTasks.value = archivedTasks.value.map(decrement)
  }

  function bumpFileCount(taskId, delta) {
    const apply = (t) =>
      t.id === taskId ? { ...t, file_count: Math.max(0, (t.file_count ?? 0) + delta) } : t
    tasks.value = tasks.value.map(apply)
    archivedTasks.value = archivedTasks.value.map(apply)
  }

  async function updateTask(bandSpaceId, taskId, data) {
    isSaving.value = true
    try {
      const updated = await bandSpaceTasksApi.updateTask(bandSpaceId, taskId, data)
      // Both lists, since the drawer reads whichever holds the task: an archived one written back
      // only to `tasks` left the drawer showing what was there before the save it just confirmed.
      tasks.value = tasks.value.map((t) => (t.id === taskId ? updated : t))
      archivedTasks.value = archivedTasks.value.map((t) => (t.id === taskId ? updated : t))
      return updated
    } finally {
      isSaving.value = false
    }
  }

  async function updateTaskOptimistic(bandSpaceId, taskId, data) {
    const snapshot = [...tasks.value]
    tasks.value = tasks.value.map((t) => (t.id === taskId ? { ...t, ...data } : t))
    try {
      const updated = await bandSpaceTasksApi.updateTask(bandSpaceId, taskId, data)
      tasks.value = tasks.value.map((t) => (t.id === taskId ? updated : t))
    } catch (e) {
      tasks.value = snapshot
      throw e
    }
  }

  /**
   * `visibleIndex` is where the card landed in the destination column as the user sees it, or
   * null to append, which is what the "Déplacer vers" menu asks for since it has no drop point.
   */
  async function moveTaskToColumn(bandSpaceId, taskId, newStatus, visibleIndex = null) {
    const snapshot = [...tasks.value]

    // Under a server-side filter the rest of the destination column is not in memory, so no
    // ordering can be sent. An empty payload asks the server to append the task instead, which
    // is the one placement it can work out on its own.
    const orderedIds = isReorderDisabled.value
      ? null
      : destinationOrder(newStatus, taskId, visibleIndex)
    const positions = orderedIds === null ? [] : toPositions(orderedIds)
    // Where the card is drawn until the server answers with the position it really got.
    const movedPosition =
      orderedIds === null ? tasksByStatus.value[newStatus].length : orderedIds.indexOf(taskId)

    tasks.value = tasks.value.map((t) => {
      if (t.id === taskId) return { ...t, status: newStatus, position: movedPosition }
      const pos = positions.find((p) => p.id === t.id)
      return pos ? { ...t, position: pos.position } : t
    })

    try {
      const updated = await bandSpaceTasksApi.moveTask(bandSpaceId, taskId, newStatus, positions)
      tasks.value = tasks.value.map((t) => (t.id === taskId ? updated : t))
    } catch (e) {
      tasks.value = snapshot
      throw e
    }
  }

  /** The whole destination column with the incoming task in place, hidden tasks included. */
  function destinationOrder(newStatus, taskId, visibleIndex) {
    const columnIds = columnOrderedIds(newStatus).filter((id) => id !== taskId)
    // No drop point: the end of the column, which is what the server does with an empty payload.
    if (visibleIndex === null) {
      return [...columnIds, taskId]
    }

    const visibleIds = tasksByStatus.value[newStatus].map((t) => t.id).filter((id) => id !== taskId)
    visibleIds.splice(visibleIndex, 0, taskId)

    return orderColumnAfterDrag(columnIds, visibleIds, taskId)
  }

  async function deleteTask(bandSpaceId, taskId) {
    isDeleting.value = true
    try {
      await bandSpaceTasksApi.deleteTask(bandSpaceId, taskId)
      tasks.value = tasks.value.filter((t) => t.id !== taskId)
      archivedTasks.value = archivedTasks.value.filter((t) => t.id !== taskId)
    } finally {
      isDeleting.value = false
    }
  }

  async function archiveTask(bandSpaceId, taskId) {
    const updated = await bandSpaceTasksApi.updateTask(bandSpaceId, taskId, { archived: true })
    tasks.value = tasks.value.filter((t) => t.id !== taskId)
    archivedTasks.value = [updated, ...archivedTasks.value]
  }

  async function unarchiveTask(bandSpaceId, taskId) {
    const updated = await bandSpaceTasksApi.updateTask(bandSpaceId, taskId, { archived: false })
    archivedTasks.value = archivedTasks.value.filter((t) => t.id !== taskId)
    tasks.value = [updated, ...tasks.value]
  }

  /**
   * `visibleOrderedIds` is the column as the user sees it after the drag, which the category,
   * assignee, priority and "Mes tâches" filters can cut down. The payload has to cover the whole
   * column, so the drag is replayed against it here.
   */
  async function reorderTasks(bandSpaceId, status, visibleOrderedIds, movedTaskId) {
    // The columns are not draggable while a server-side filter is on, so this is a backstop.
    if (isReorderDisabled.value) return

    const orderedIds = orderColumnAfterDrag(
      columnOrderedIds(status),
      visibleOrderedIds,
      movedTaskId
    )
    const positions = toPositions(orderedIds)

    // Optimistic update
    const snapshot = [...tasks.value]
    tasks.value = tasks.value.map((t) => {
      const pos = positions.find((p) => p.id === t.id)
      return pos ? { ...t, position: pos.position } : t
    })

    try {
      await bandSpaceTasksApi.reorderTasks(bandSpaceId, positions)
    } catch (e) {
      tasks.value = snapshot
      throw e
    }
  }

  async function createCategory(bandSpaceId, data) {
    const created = await bandSpaceTasksApi.createCategory(bandSpaceId, data)
    categories.value = [...categories.value, created]
    return created
  }

  async function updateCategory(bandSpaceId, categoryId, data) {
    const updated = await bandSpaceTasksApi.updateCategory(bandSpaceId, categoryId, data)
    categories.value = categories.value.map((c) => (c.id === categoryId ? updated : c))
    return updated
  }

  async function deleteCategory(bandSpaceId, categoryId) {
    await bandSpaceTasksApi.deleteCategory(bandSpaceId, categoryId)
    categories.value = categories.value.filter((c) => c.id !== categoryId)
    tasks.value = tasks.value.map((t) =>
      t.category_id === categoryId ? { ...t, category_id: null, category_name: null } : t
    )
    archivedTasks.value = archivedTasks.value.map((t) =>
      t.category_id === categoryId ? { ...t, category_id: null, category_name: null } : t
    )
  }

  function setActiveTask(taskId, bandSpaceId = null) {
    activeTaskId.value = taskId || null
    if (!taskId) {
      activeTaskError.value = null
      isLoadingActiveTask.value = false
      return
    }
    const alreadyLoaded =
      tasks.value.some((t) => t.id === taskId) || archivedTasks.value.some((t) => t.id === taskId)
    if (!alreadyLoaded && bandSpaceId) {
      fetchTaskById(bandSpaceId, taskId)
    }
  }

  function setFilter(key, value) {
    filters[key] = value
  }

  function enterSelectionMode() {
    isSelectionMode.value = true
  }

  function exitSelectionMode() {
    isSelectionMode.value = false
    selectedTaskIds.value = new Set()
  }

  function toggleTaskSelection(taskId) {
    const next = new Set(selectedTaskIds.value)
    if (next.has(taskId)) {
      next.delete(taskId)
    } else {
      next.add(taskId)
    }
    selectedTaskIds.value = next
  }

  async function bulkPatch(bandSpaceId, patch) {
    const payload = { task_ids: [...selectedTaskIds.value], ...patch }
    await bandSpaceTasksApi.bulkPatchTasks(bandSpaceId, payload)
    await fetchTasks(bandSpaceId)
    if (filters.showArchived) {
      await fetchArchivedTasks(bandSpaceId)
    }
    exitSelectionMode()
  }

  async function bulkDelete(bandSpaceId) {
    const ids = [...selectedTaskIds.value]
    await bandSpaceTasksApi.bulkDeleteTasks(bandSpaceId, ids)
    tasks.value = tasks.value.filter((t) => !ids.includes(t.id))
    archivedTasks.value = archivedTasks.value.filter((t) => !ids.includes(t.id))
    exitSelectionMode()
  }

  function clear() {
    tasks.value = []
    archivedTasks.value = []
    categories.value = []
    members.value = []
    activeTaskId.value = null
    isSelectionMode.value = false
    selectedTaskIds.value = new Set()
    filters.categoryId = null
    filters.assigneeId = null
    filters.unassigned = false
    filters.priority = null
    filters.myTasks = false
    filters.showArchived = false
    filters.query = ''
    filters.dueDateFrom = null
    filters.dueDateTo = null
    filters.overdue = false
    loadError.value = null
    isLoadingActiveTask.value = false
    activeTaskError.value = null
  }

  return {
    tasks: readonly(tasks),
    archivedTasks: readonly(archivedTasks),
    categories: readonly(categories),
    members: readonly(members),
    activeTaskId: readonly(activeTaskId),
    isSelectionMode: readonly(isSelectionMode),
    selectedTaskIds: readonly(selectedTaskIds),
    filters: readonly(filters),
    isLoading: readonly(isLoading),
    isCreating: readonly(isCreating),
    isSaving: readonly(isSaving),
    isDeleting: readonly(isDeleting),
    loadError: readonly(loadError),
    isLoadingActiveTask: readonly(isLoadingActiveTask),
    activeTaskError: readonly(activeTaskError),
    isReorderDisabled,
    tasksByStatus,
    activeTask,
    fetchTasks,
    fetchTaskById,
    fetchArchivedTasks,
    fetchCategories,
    fetchMembers,
    createTask,
    createComment,
    updateComment,
    deleteComment,
    bumpFileCount,
    updateTask,
    updateTaskOptimistic,
    moveTaskToColumn,
    deleteTask,
    archiveTask,
    unarchiveTask,
    reorderTasks,
    createCategory,
    updateCategory,
    deleteCategory,
    setActiveTask,
    setFilter,
    enterSelectionMode,
    exitSelectionMode,
    toggleTaskSelection,
    bulkPatch,
    bulkDelete,
    clear
  }
})
