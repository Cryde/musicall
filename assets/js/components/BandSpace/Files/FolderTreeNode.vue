<template>
  <div :style="{ paddingLeft: `${folder.depth * 12}px` }" class="flex flex-col gap-1">
    <button
      type="button"
      class="flex items-center gap-2 px-3 py-2 rounded-md text-sm text-left transition-colors duration-150"
      :class="buttonClasses"
      @click="emit('select', folder.id)"
    >
      <i class="pi pi-folder text-surface-500"></i>
      <span class="flex-1 truncate">{{ folder.name }}</span>
    </button>

    <FolderTreeNode
      v-for="child in folder.children"
      :key="child.id"
      :folder="child"
      :active-id="activeId"
      @select="(id) => emit('select', id)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  folder: { type: Object, required: true },
  activeId: { type: String, default: null }
})

const emit = defineEmits(['select'])

const buttonClasses = computed(() => {
  return props.activeId === props.folder.id
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
})
</script>
