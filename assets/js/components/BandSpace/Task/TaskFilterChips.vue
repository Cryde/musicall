<template>
  <div class="flex items-center gap-x-4 gap-y-2 flex-wrap">
    <!-- Category filters -->
    <div v-if="categories.length > 0" class="flex flex-wrap items-center gap-1.5">
      <button
        v-for="cat in categories"
        :key="cat.id"
        class="text-xs font-medium px-2 py-1 rounded-full border transition-colors"
        :class="
          filters.categoryId === cat.id
            ? 'border-transparent text-white'
            : 'border-surface-200 dark:border-surface-600 text-surface-600 dark:text-surface-300 hover:border-surface-400'
        "
        :style="filters.categoryId === cat.id ? { backgroundColor: cat.color } : {}"
        @click="toggleFilter('categoryId', cat.id)"
      >
        <span
          v-if="filters.categoryId !== cat.id"
          class="inline-block w-2 h-2 rounded-full mr-1"
          :style="{ backgroundColor: cat.color }"
        ></span>
        {{ cat.name }}
      </button>
    </div>

    <!-- Assignee filters -->
    <div v-if="members.length > 0" class="flex flex-wrap items-center gap-1">
      <button
        v-for="member in members"
        :key="member.id"
        type="button"
        class="rounded-full transition-all"
        :class="
          filters.assigneeId === member.user_id
            ? 'ring-2 ring-primary'
            : 'hover:ring-1 hover:ring-surface-400'
        "
        :title="member.display_name"
        @click="toggleFilter('assigneeId', member.user_id)"
      >
        <Avatar
          :username="member.username"
          :display-name="member.display_name"
          :picture-url="member.profile_picture_url"
          size="md"
        />
      </button>
    </div>

    <!-- Unassigned filter: the tasks nobody is on, incl. those a departed member left behind -->
    <button
      type="button"
      class="text-xs px-2 py-1 rounded border transition-colors"
      :class="
        filters.unassigned
          ? 'bg-primary text-primary-contrast border-primary'
          : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
      "
      @click="$emit('update-filter', 'unassigned', !filters.unassigned)"
    >
      <i class="pi pi-user-minus text-[10px] mr-1"></i>
      {{ t('band_space_tasks.filter_bar.unassigned') }}
    </button>

    <!-- Priority filters -->
    <div class="flex items-center gap-1">
      <button
        v-for="p in priorities"
        :key="p.value"
        class="text-xs px-2 py-1 rounded border transition-colors"
        :class="
          filters.priority === p.value
            ? p.activeClass
            : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
        "
        @click="toggleFilter('priority', p.value)"
      >
        {{ t(p.labelKey) }}
      </button>
    </div>

    <!-- Quick toggles -->
    <div class="flex items-center gap-1">
      <button
        v-if="withMyTasks"
        class="text-xs px-2 py-1 rounded border transition-colors"
        :class="
          filters.myTasks
            ? 'bg-primary text-primary-contrast border-primary'
            : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
        "
        @click="$emit('update-filter', 'myTasks', !filters.myTasks)"
      >
        {{ t('band_space_tasks.filter_bar.my_tasks') }}
      </button>
      <button
        class="text-xs px-2 py-1 rounded border transition-colors"
        :class="
          filters.overdue
            ? 'bg-red-600 text-white border-red-600'
            : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
        "
        @click="$emit('update-filter', 'overdue', !filters.overdue)"
      >
        <i class="pi pi-clock text-[10px] mr-1"></i>
        {{ t('band_space_tasks.filter_bar.overdue') }}
      </button>
      <button
        class="text-xs px-2 py-1 rounded border transition-colors"
        :class="
          filters.showArchived
            ? 'bg-surface-600 text-white border-surface-600'
            : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
        "
        @click="$emit('update-filter', 'showArchived', !filters.showArchived)"
      >
        <i class="pi pi-box text-[10px] mr-1"></i>
        {{ t('band_space_tasks.filter_bar.archived') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import Avatar from '../../User/Avatar.vue'

/**
 * The board's filters as chips: categories, members, « Non assignées », priority, « Mes tâches »,
 * « En retard » and the archive. The desktop bar's second row, and the phone's « Filtres » sheet,
 * which leaves « Mes tâches » to its own chip on the screen.
 */
const props = defineProps({
  categories: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  filters: { type: Object, required: true },
  withMyTasks: { type: Boolean, default: true }
})

const emit = defineEmits(['update-filter'])

const { t } = useI18n()

const priorities = [
  {
    value: 'urgent',
    labelKey: 'band_space_tasks.priority.urgent',
    activeClass:
      'bg-red-100 text-red-700 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-700'
  },
  {
    value: 'high',
    labelKey: 'band_space_tasks.priority.high',
    activeClass:
      'bg-orange-100 text-orange-700 border-orange-300 dark:bg-orange-900 dark:text-orange-300 dark:border-orange-700'
  },
  {
    value: 'normal',
    labelKey: 'band_space_tasks.priority.normal',
    activeClass:
      'bg-surface-200 text-surface-700 border-surface-400 dark:bg-surface-600 dark:text-surface-200'
  }
]

function toggleFilter(key, value) {
  emit('update-filter', key, props.filters[key] === value ? null : value)
}
</script>
