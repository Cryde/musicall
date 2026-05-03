import { defineStore } from 'pinia'
import { computed, reactive, readonly, ref } from 'vue'
import bandSpaceSettingsApi from '../../api/bandSpace/band-space-settings.js'
import bandSpaceTasksApi from '../../api/bandSpace/band-space-tasks.js'
import { useUserSecurityStore } from '../user/security.js'

export const useBandTasksStore = defineStore('bandTasks', () => {
  const tasks = ref([])
  const archivedTasks = ref([])
  const categories = ref([])
  const members = ref([])
  const activeTaskId = ref(null)
  const filters = reactive({
    categoryId: null,
    assigneeId: null,
    priority: null,
    myTasks: false,
    showArchived: false,
    query: ''
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
      const result = await bandSpaceTasksApi.getTasks(
        bandSpaceId,
        trimmedQuery ? { query: trimmedQuery } : {}
      )
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

  async function updateTask(bandSpaceId, taskId, data) {
    isSaving.value = true
    try {
      const updated = await bandSpaceTasksApi.updateTask(bandSpaceId, taskId, data)
      tasks.value = tasks.value.map((t) => (t.id === taskId ? updated : t))
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

  async function moveTaskToColumn(bandSpaceId, taskId, newStatus, newIndex) {
    const snapshot = [...tasks.value]
    const destinationIds = tasks.value
      .filter((t) => t.status === newStatus && !t.archive_datetime && t.id !== taskId)
      .sort((a, b) => a.position - b.position)
      .map((t) => t.id)
    destinationIds.splice(newIndex, 0, taskId)
    const positions = destinationIds.map((id, index) => ({ id, position: index }))

    tasks.value = tasks.value.map((t) => {
      const pos = positions.find((p) => p.id === t.id)
      if (t.id === taskId) return { ...t, status: newStatus, position: newIndex }
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

  async function deleteTask(bandSpaceId, taskId) {
    isDeleting.value = true
    try {
      await bandSpaceTasksApi.deleteTask(bandSpaceId, taskId)
      tasks.value = tasks.value.filter((t) => t.id !== taskId)
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

  async function reorderTasks(bandSpaceId, status, orderedIds) {
    const positions = orderedIds.map((id, index) => ({ id, position: index }))

    // Optimistic update
    const snapshot = [...tasks.value]
    tasks.value = tasks.value.map((t) => {
      if (t.status !== status) return t
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

  function clear() {
    tasks.value = []
    archivedTasks.value = []
    categories.value = []
    members.value = []
    activeTaskId.value = null
    filters.categoryId = null
    filters.assigneeId = null
    filters.priority = null
    filters.myTasks = false
    filters.showArchived = false
    filters.query = ''
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
    filters: readonly(filters),
    isLoading: readonly(isLoading),
    isCreating: readonly(isCreating),
    isSaving: readonly(isSaving),
    isDeleting: readonly(isDeleting),
    loadError: readonly(loadError),
    isLoadingActiveTask: readonly(isLoadingActiveTask),
    activeTaskError: readonly(activeTaskError),
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
    clear
  }
})
