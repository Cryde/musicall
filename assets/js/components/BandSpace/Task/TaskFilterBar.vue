<template>
  <div class="flex items-center gap-3 flex-wrap mb-4">
    <!-- Category filters -->
    <div class="flex items-center gap-1.5">
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
    <div class="flex items-center gap-1">
      <button
        v-for="member in members"
        :key="member.id"
        class="w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-semibold transition-all"
        :class="
          filters.assigneeId === member.user_id
            ? 'ring-2 ring-primary bg-primary text-primary-contrast'
            : 'bg-surface-200 dark:bg-surface-700 text-surface-600 dark:text-surface-300 hover:ring-1 hover:ring-surface-400'
        "
        :title="member.username"
        @click="toggleFilter('assigneeId', member.user_id)"
      >
        {{ member.username.charAt(0).toUpperCase() }}
      </button>
    </div>

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
        {{ p.label }}
      </button>
    </div>

    <!-- My tasks toggle -->
    <button
      class="text-xs px-2 py-1 rounded border transition-colors"
      :class="
        filters.myTasks
          ? 'bg-primary text-primary-contrast border-primary'
          : 'border-surface-200 dark:border-surface-600 text-surface-500 hover:border-surface-400'
      "
      @click="$emit('update-filter', 'myTasks', !filters.myTasks)"
    >
      Mes tâches
    </button>

    <!-- Show archived toggle -->
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
      Archivées
    </button>

    <div class="flex-1"></div>

    <!-- Actions -->
    <Button
      icon="pi pi-cog"
      text
      rounded
      size="small"
      v-tooltip.bottom="'Gérer les catégories'"
      @click="$emit('open-categories')"
    />
    <Button
      label="Nouvelle tâche"
      icon="pi pi-plus"
      size="small"
      @click="$emit('create-task')"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  filters: { type: Object, required: true }
})

const emit = defineEmits(['update-filter', 'open-categories', 'create-task'])

const priorities = [
  { value: 'urgent', label: 'Urgent', activeClass: 'bg-red-100 text-red-700 border-red-300 dark:bg-red-900 dark:text-red-300 dark:border-red-700' },
  { value: 'high', label: 'Haute', activeClass: 'bg-orange-100 text-orange-700 border-orange-300 dark:bg-orange-900 dark:text-orange-300 dark:border-orange-700' },
  { value: 'normal', label: 'Normal', activeClass: 'bg-surface-200 text-surface-700 border-surface-400 dark:bg-surface-600 dark:text-surface-200' }
]

function toggleFilter(key, value) {
  emit('update-filter', key, props.filters[key] === value ? null : value)
}
</script>
