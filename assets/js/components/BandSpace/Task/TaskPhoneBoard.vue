<template>
  <div>
    <div class="flex items-center gap-1 pb-2">
      <h1 class="flex-1 text-2xl font-semibold text-surface-900 dark:text-surface-0">
        {{ t('band_space_tasks.phone.title') }}
      </h1>
      <Button
        :icon="isSearching ? 'pi pi-times' : 'pi pi-search'"
        text
        rounded
        severity="secondary"
        :aria-label="isSearching ? t('band_space_tasks.phone.close_search') : t('band_space_tasks.phone.search')"
        :aria-expanded="isSearching"
        @click="toggleSearch"
      />
      <Button
        :icon="tasksStore.isSelectionMode ? 'pi pi-times' : 'pi pi-check-square'"
        text
        rounded
        severity="secondary"
        :aria-label="t('band_space_tasks.filter_bar.select')"
        :aria-pressed="tasksStore.isSelectionMode"
        @click="toggleSelectionMode"
      />
    </div>

    <IconField v-if="isSearching" class="mb-3">
      <InputIcon class="pi pi-search" />
      <InputText
        ref="searchField"
        v-model="searchInput"
        type="search"
        :placeholder="t('band_space_tasks.phone.search')"
        :aria-label="t('band_space_tasks.phone.search')"
        class="w-full"
        @input="searchSoon"
      />
    </IconField>

    <div class="flex flex-wrap gap-2 pb-3">
      <button
        type="button"
        :class="[PHONE_CHIP, filters.myTasks ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
        :aria-pressed="filters.myTasks"
        @click="$emit('update-filter', 'myTasks', !filters.myTasks)"
      >
        {{ t('band_space_tasks.filter_bar.my_tasks') }}
      </button>
      <button
        type="button"
        :class="[PHONE_CHIP, activeFilterCount > 0 ? PHONE_CHIP_ON : PHONE_CHIP_OFF]"
        aria-haspopup="dialog"
        :aria-expanded="isFilterSheetOpen"
        @click="isFilterSheetOpen = true"
      >
        <i class="pi pi-sliders-h text-xs" aria-hidden="true" />
        {{
          activeFilterCount > 0
            ? t('band_space_tasks.phone.filters_active', { count: activeFilterCount })
            : t('band_space_tasks.phone.filters')
        }}
      </button>
    </div>

    <template v-if="showTabs">
      <Message v-if="tasksStore.isReorderDisabled" severity="info" :closable="false" class="mb-3">
        {{ t('band_space_tasks.board.reorder_disabled') }}
      </Message>

      <!-- One status at a time, as in the app: tapped, swiped, or moved through with the arrow keys. -->
      <div
        role="tablist"
        :aria-label="t('band_space_tasks.phone.tabs')"
        class="-mx-6 flex border-b border-surface-300 bg-surface-0 dark:border-surface-700 dark:bg-surface-900"
        @keydown="handleTabKeydown"
      >
        <button
          v-for="status in TASK_STATUSES"
          :id="`task-tab-${status.value}`"
          :key="status.value"
          ref="tabs"
          type="button"
          role="tab"
          aria-controls="task-tab-panel"
          :aria-selected="activeStatus === status.value"
          :tabindex="activeStatus === status.value ? 0 : -1"
          class="flex h-12 flex-1 items-center justify-center gap-1.5 border-b-2 text-sm font-semibold transition-colors"
          :class="
            activeStatus === status.value
              ? 'border-primary text-surface-900 dark:text-surface-0'
              : 'border-transparent text-surface-600 dark:text-surface-400'
          "
          @click="selectStatus(status.value)"
        >
          {{ t(status.labelKey) }}
          <span class="rounded-full bg-surface-100 px-1.5 text-xs font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300">
            {{ (tasksByStatus[status.value] || []).length }}
          </span>
        </button>
      </div>

      <div
        id="task-tab-panel"
        ref="panel"
        role="tabpanel"
        :aria-labelledby="`task-tab-${activeStatus}`"
        class="min-h-[50vh] pt-3 pb-24"
      >
        <TaskColumn
          :key="activeStatus"
          compact
          :status="activeStatus"
          :tasks="tasksByStatus[activeStatus] || []"
          :label="activeLabel"
          :categories="categories"
          :band-space-id="bandSpaceId"
          @open-task="$emit('open-task', $event)"
          @pick-status="openStatusSheet"
          @reorder="(status, ids, movedId) => $emit('reorder', status, ids, movedId)"
          @status-change="(taskId, status, visibleIndex) => $emit('status-change', taskId, status, visibleIndex)"
          @show-all-done="$emit('show-all-done')"
        />
      </div>
    </template>

    <!-- The bulk bar takes the bottom of the screen while tasks are being selected. -->
    <Button
      v-if="!tasksStore.isSelectionMode"
      :label="t('band_space_tasks.common.new_task')"
      icon="pi pi-plus"
      rounded
      class="fixed! right-5 bottom-[calc(1.5rem+env(safe-area-inset-bottom))] z-10 shadow-lg"
      @click="$emit('create-task')"
    />

    <TaskStatusSheet v-model:visible="isStatusSheetOpen" :task="statusSheetTask" @choose="moveStatusSheetTask" />

    <BottomSheet v-model:visible="isFilterSheetOpen" :title="t('band_space_tasks.phone.filters')">
      <div class="flex flex-col gap-4 px-5 pb-4">
        <TaskFilterChips
          :categories="categories"
          :members="members"
          :filters="filters"
          :with-my-tasks="false"
          @update-filter="(key, value) => $emit('update-filter', key, value)"
        />
        <TaskDueRangePicker
          :from="filters.dueDateFrom"
          :to="filters.dueDateTo"
          input-class="w-full text-sm"
          @update-filter="(key, value) => $emit('update-filter', key, value)"
        />
      </div>
      <SheetAction icon="pi-cog" @click="openCategories">{{ t('band_space_tasks.common.manage_categories') }}</SheetAction>
    </BottomSheet>
  </div>
</template>

<script setup>
import { useSwipe } from '@vueuse/core'
import Button from 'primevue/button'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Message from 'primevue/message'
import { useToast } from 'primevue/usetoast'
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRoute, useRouter } from 'vue-router'
import { useTaskSearchInput } from '../../../composables/useTaskSearchInput.js'
import { PHONE_CHIP, PHONE_CHIP_OFF, PHONE_CHIP_ON } from '../../../constants/phoneControls.js'
import { TASK_STATUSES } from '../../../constants/taskStatuses.js'
import { useBandTasksStore } from '../../../store/bandSpace/bandSpaceTasks.js'
import { sheetFilterCount } from '../../../utils/taskFilters.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'
import TaskColumn from './TaskColumn.vue'
import TaskDueRangePicker from './TaskDueRangePicker.vue'
import TaskFilterChips from './TaskFilterChips.vue'
import TaskStatusSheet from './TaskStatusSheet.vue'

/**
 * The tasks on a phone, as the Android app has them: one status at a time in tabs with their counts,
 * a status mark on each task, the filters in a sheet, « Nouvelle tâche » floating at the bottom. The
 * board does the same work from `md` up.
 */
const props = defineProps({
  tasksByStatus: { type: Object, required: true },
  categories: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
  bandSpaceId: { type: String, required: true },
  // Off while the archive or every done task is listed in the tabs' place.
  showTabs: { type: Boolean, default: true }
})

const emit = defineEmits([
  'open-task',
  'reorder',
  'status-change',
  'show-all-done',
  'update-filter',
  'open-categories',
  'create-task'
])

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const tasksStore = useBandTasksStore()
const toast = useToast()

// ---------- Tabs ----------

const STATUS_VALUES = TASK_STATUSES.map((status) => status.value)

// In the address, so coming back from a task, or reloading, lands on the same tab.
const activeStatus = computed(() =>
  STATUS_VALUES.includes(route.query.statut) ? route.query.statut : STATUS_VALUES[0]
)
const activeLabel = computed(() =>
  t(TASK_STATUSES.find((status) => status.value === activeStatus.value).labelKey)
)

function selectStatus(status) {
  if (status === activeStatus.value) return
  router.replace({
    query: { ...route.query, statut: status === STATUS_VALUES[0] ? undefined : status }
  })
}

const tabs = useTemplateRef('tabs')

async function handleTabKeydown(event) {
  const step = { ArrowRight: 1, ArrowLeft: -1 }[event.key]
  if (!step) return
  event.preventDefault()
  const index =
    (STATUS_VALUES.indexOf(activeStatus.value) + step + STATUS_VALUES.length) % STATUS_VALUES.length
  selectStatus(STATUS_VALUES[index])
  await nextTick()
  tabs.value?.[index]?.focus()
}

// A swipe across the list goes to the next or the previous status, but not the end of a card's drag.
const panel = useTemplateRef('panel')
let swipeFollowsDrag = false
const SIDEWAYS_RATIO = 1.5

watch(
  () => tasksStore.isDragging,
  (isDragging) => {
    if (isDragging) swipeFollowsDrag = true
  }
)

const { lengthX, lengthY } = useSwipe(panel, {
  threshold: 60,
  onSwipeStart() {
    swipeFollowsDrag = tasksStore.isDragging
  },
  onSwipeEnd(_event, direction) {
    // Clearly sideways only, or a diagonal scroll down the list would change the tab.
    if (swipeFollowsDrag || Math.abs(lengthX.value) < SIDEWAYS_RATIO * Math.abs(lengthY.value))
      return
    const step = { left: 1, right: -1 }[direction]
    const index = STATUS_VALUES.indexOf(activeStatus.value) + (step ?? 0)
    if (step && index >= 0 && index < STATUS_VALUES.length) selectStatus(STATUS_VALUES[index])
  }
})

// ---------- Search, selection, filters ----------

const { searchInput, searchSoon } = useTaskSearchInput(
  () => props.filters.query,
  (text) => emit('update-filter', 'query', text)
)
const isSearching = ref(searchInput.value !== '')
const searchField = useTemplateRef('searchField')

// Closing the field drops the search too, rather than leaving the list cut down by text out of sight.
async function toggleSearch() {
  isSearching.value = !isSearching.value
  if (isSearching.value) {
    await nextTick()
    searchField.value?.$el?.focus()
  } else if (searchInput.value !== '') {
    searchInput.value = ''
    emit('update-filter', 'query', '')
  }
}

function toggleSelectionMode() {
  if (tasksStore.isSelectionMode) tasksStore.exitSelectionMode()
  else tasksStore.enterSelectionMode()
}

const activeFilterCount = computed(() => sheetFilterCount(props.filters))
const isFilterSheetOpen = ref(false)

function openCategories() {
  isFilterSheetOpen.value = false
  emit('open-categories')
}

// ---------- The status sheet ----------

const isStatusSheetOpen = ref(false)
const statusSheetTaskId = ref(null)
// Read back from the board, so the sheet follows the task if a live refresh changes it.
const statusSheetTask = computed(
  () =>
    Object.values(props.tasksByStatus)
      .flat()
      .find((task) => task.id === statusSheetTaskId.value) ?? null
)

function openStatusSheet(task) {
  statusSheetTaskId.value = task.id
  isStatusSheetOpen.value = true
}

// To the end of the chosen status, as the desktop card's « Déplacer vers » does.
async function moveStatusSheetTask(status) {
  try {
    await tasksStore.moveTaskToColumn(props.bandSpaceId, statusSheetTaskId.value, status, null)
  } catch {
    toast.add({ severity: 'error', summary: t('band_space_tasks.common.move_failed'), life: 5000 })
  }
}
</script>
