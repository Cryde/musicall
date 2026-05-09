<template>
  <div :style="{ paddingLeft: `${folder.depth * 12}px` }" class="flex flex-col gap-1">
    <div
      class="group relative flex items-center h-8 px-2 rounded-md text-sm transition-colors duration-150"
      :class="rowClasses"
    >
      <button
        type="button"
        class="flex items-center gap-2 w-full min-w-0 text-left h-full pr-1 group-hover:pr-20"
        @click="emit('select', folder.id)"
      >
        <i class="pi pi-folder text-surface-500 shrink-0"></i>
        <span class="truncate">{{ folder.name }}</span>
      </button>
      <div
        class="absolute right-1 top-1/2 -translate-y-1/2 hidden group-hover:flex items-center gap-0.5"
      >
        <button
          type="button"
          class="w-6 h-6 flex items-center justify-center rounded text-surface-500 hover:bg-surface-200 dark:hover:bg-surface-700 disabled:opacity-30 disabled:cursor-not-allowed"
          v-tooltip.top="canCreateSub ? 'Nouveau sous-dossier' : 'Profondeur maximale atteinte'"
          :disabled="!canCreateSub"
          @click.stop="emit('create-sub', folder)"
        >
          <i class="pi pi-plus text-xs"></i>
        </button>
        <button
          type="button"
          class="w-6 h-6 flex items-center justify-center rounded text-surface-500 hover:bg-surface-200 dark:hover:bg-surface-700"
          v-tooltip.top="'Renommer / déplacer'"
          @click.stop="emit('edit', folder)"
        >
          <i class="pi pi-pencil text-xs"></i>
        </button>
        <button
          type="button"
          class="w-6 h-6 flex items-center justify-center rounded text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40"
          v-tooltip.top="'Supprimer'"
          @click.stop="emit('delete', folder)"
        >
          <i class="pi pi-trash text-xs"></i>
        </button>
      </div>
    </div>

    <FolderTreeNode
      v-for="child in folder.children"
      :key="child.id"
      :folder="child"
      :active-id="activeId"
      @select="(id) => emit('select', id)"
      @create-sub="(node) => emit('create-sub', node)"
      @edit="(node) => emit('edit', node)"
      @delete="(node) => emit('delete', node)"
    />
  </div>
</template>

<script setup>
import { computed } from 'vue'

const MAX_DEPTH = 6

const props = defineProps({
  folder: { type: Object, required: true },
  activeId: { type: String, default: null }
})

const emit = defineEmits(['select', 'create-sub', 'edit', 'delete'])

const rowClasses = computed(() => {
  return props.activeId === props.folder.id
    ? 'bg-surface-100 dark:bg-surface-800 text-surface-900 dark:text-surface-100 font-medium'
    : 'hover:bg-surface-50 dark:hover:bg-surface-800 text-surface-700 dark:text-surface-300'
})

const canCreateSub = computed(() => (props.folder.depth ?? 0) < MAX_DEPTH - 1)
</script>
