<template>
  <div :style="{ paddingLeft: `${folder.depth * 12}px` }" class="flex flex-col gap-1">
    <div
      class="group flex items-center gap-1 px-2 py-1.5 rounded-md text-sm transition-colors duration-150"
      :class="rowClasses"
    >
      <button
        type="button"
        class="flex items-center gap-2 flex-1 min-w-0 text-left"
        @click="emit('select', folder.id)"
      >
        <i class="pi pi-folder text-surface-500"></i>
        <span class="truncate">{{ folder.name }}</span>
      </button>
      <div class="hidden group-hover:flex items-center gap-0.5 shrink-0">
        <Button
          icon="pi pi-plus"
          size="small"
          text
          rounded
          v-tooltip.top="canCreateSub ? 'Nouveau sous-dossier' : 'Profondeur maximale atteinte'"
          :disabled="!canCreateSub"
          @click.stop="emit('create-sub', folder)"
        />
        <Button
          icon="pi pi-pencil"
          size="small"
          text
          rounded
          v-tooltip.top="'Renommer / déplacer'"
          @click.stop="emit('edit', folder)"
        />
        <Button
          icon="pi pi-trash"
          size="small"
          text
          rounded
          severity="danger"
          v-tooltip.top="'Supprimer'"
          @click.stop="emit('delete', folder)"
        />
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
import Button from 'primevue/button'
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
