<template>
  <!-- A task's fields on a phone, as the app lists them: one row each, opening a sheet to change it. -->
  <ul class="-mx-5 m-0 list-none divide-y divide-surface-200 border-y border-surface-200 p-0 dark:divide-surface-700 dark:border-surface-700">
    <li v-for="row in rows" :key="row.key">
      <!-- Plain text on an archived task, so its values stay readable rather than leaving the tab order
           with a disabled button. -->
      <component
        :is="disabled ? 'div' : 'button'"
        :type="disabled ? undefined : 'button'"
        class="flex min-h-13 w-full items-center gap-3.5 px-5 text-left text-sm transition-colors"
        :class="{ 'hover:bg-surface-100 dark:hover:bg-surface-800': !disabled }"
        :aria-haspopup="disabled ? undefined : 'dialog'"
        @click="disabled || (openSheet = row.key)"
      >
        <i class="pi w-4 text-center text-surface-500 dark:text-surface-400" :class="row.icon" aria-hidden="true" />
        <span class="w-24 shrink-0 text-surface-600 dark:text-surface-300">{{ row.label }}</span>
        <span class="flex min-w-0 flex-1 items-center justify-end gap-2 text-surface-900 dark:text-surface-0">
          <slot :name="row.key" />
        </span>
        <i v-if="!disabled" class="pi pi-chevron-right text-xs text-surface-500" aria-hidden="true" />
      </component>
    </li>
  </ul>

  <BottomSheet :visible="openSheet === 'priority'" :title="t('band_space_tasks.field.priority')" @update:visible="close">
    <ul class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
      <li v-for="option in priorityOptions" :key="option.value">
        <button type="button" :class="CHOICE" :aria-pressed="priority === option.value" @click="choose('priority', option.value)">
          {{ option.label }}
          <i v-if="priority === option.value" class="pi pi-check ml-auto text-primary" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </BottomSheet>

  <BottomSheet :visible="openSheet === 'due'" :title="t('band_space_tasks.field.due')" @update:visible="close">
    <div class="flex justify-center px-3 pb-2">
      <DatePicker :model-value="dueDate" inline @update:model-value="(date) => choose('due', date)" />
    </div>
    <SheetAction v-if="dueDate" icon="pi-times" @click="choose('due', null)">{{ t('band_space_tasks.detail.clear_due') }}</SheetAction>
  </BottomSheet>

  <BottomSheet :visible="openSheet === 'assignees'" :title="t('band_space_tasks.field.assignees')" @update:visible="close">
    <ul class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
      <li v-for="member in members" :key="member.user_id">
        <!-- Each tap saves, as the desktop's multi select does, and the sheet stays open for the next. -->
        <button type="button" :class="CHOICE" :aria-pressed="assigneeIds.includes(member.user_id)" @click="toggleAssignee(member.user_id)">
          <Avatar :username="member.username" :display-name="member.display_name" :picture-url="member.profile_picture_url" size="sm" />
          {{ member.display_name }}
          <i v-if="assigneeIds.includes(member.user_id)" class="pi pi-check ml-auto text-primary" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </BottomSheet>

  <BottomSheet :visible="openSheet === 'category'" :title="t('band_space_tasks.field.category')" @update:visible="close">
    <ul class="m-0 list-none divide-y divide-surface-200 p-0 dark:divide-surface-700">
      <li>
        <button type="button" :class="CHOICE" :aria-pressed="!categoryId" @click="choose('category', null)">
          {{ t('band_space_tasks.field.no_category') }}
          <i v-if="!categoryId" class="pi pi-check ml-auto text-primary" aria-hidden="true" />
        </button>
      </li>
      <li v-for="category in categories" :key="category.id">
        <button type="button" :class="CHOICE" :aria-pressed="categoryId === category.id" @click="choose('category', category.id)">
          <span class="size-2.5 shrink-0 rounded-full" :style="{ backgroundColor: category.color }" aria-hidden="true" />
          {{ category.name }}
          <i v-if="categoryId === category.id" class="pi pi-check ml-auto text-primary" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </BottomSheet>
</template>

<script setup>
import DatePicker from 'primevue/datepicker'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'
import Avatar from '../../User/Avatar.vue'

/**
 * Priority, due date, assignees and category of a task on a phone: a row each with its value (filled
 * by the parent's slots), and a sheet to change it. A choice is handed back with `change`; the task
 * drawer saves it the way its desktop fields do.
 */
const props = defineProps({
  priority: { type: String, required: true },
  dueDate: { type: Date, default: null },
  assigneeIds: { type: Array, default: () => [] },
  categoryId: { type: String, default: null },
  priorityOptions: { type: Array, required: true },
  categories: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  // An archived task: the rows say what it holds, and open nothing.
  disabled: { type: Boolean, default: false }
})

const emit = defineEmits(['change'])

const CHOICE =
  'flex w-full items-center gap-3 px-5 py-3.5 text-left text-surface-900 transition-colors hover:bg-surface-100 dark:text-surface-0 dark:hover:bg-surface-800'

const { t } = useI18n()
// Which sheet is open, for the drawer holding the rows: one Escape should close the sheet, not both.
const openSheet = defineModel('openSheet', { type: String, default: null })

const rows = computed(() => [
  { key: 'priority', icon: 'pi-flag', label: t('band_space_tasks.field.priority') },
  { key: 'due', icon: 'pi-calendar', label: t('band_space_tasks.field.due') },
  { key: 'assignees', icon: 'pi-users', label: t('band_space_tasks.field.assignees') },
  { key: 'category', icon: 'pi-tag', label: t('band_space_tasks.field.category') }
])

function close(isVisible) {
  if (!isVisible) openSheet.value = null
}

function choose(field, value) {
  openSheet.value = null
  emit('change', field, value)
}

function toggleAssignee(userId) {
  const ids = props.assigneeIds.includes(userId)
    ? props.assigneeIds.filter((id) => id !== userId)
    : [...props.assigneeIds, userId]
  emit('change', 'assignees', ids)
}
</script>
