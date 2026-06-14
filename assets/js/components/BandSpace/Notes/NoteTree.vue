<template>
  <div class="flex flex-col h-full">
    <div class="flex items-center justify-between p-3 border-b border-surface-200 dark:border-surface-700">
      <span class="font-semibold text-surface-700 dark:text-surface-200">Notes</span>
      <Button
        v-tooltip.bottom="'Nouvelle note'"
        icon="pi pi-plus"
        severity="secondary"
        text
        rounded
        size="small"
        @click="emit('create-root')"
      />
    </div>
    <div class="flex-1 overflow-y-auto overflow-x-hidden p-2">
      <Tree
        v-if="nodes.length > 0"
        :value="nodes"
        selectionMode="single"
        v-model:selectionKeys="selectionKeys"
        v-model:expandedKeys="expandedKeys"
        class="w-full border-0 p-0 bg-transparent note-tree"
        @node-select="handleSelect"
      >
        <template #default="{ node }">
          <div class="flex items-center w-full min-w-0">
            <span class="text-sm min-w-0 flex-1 truncate">
              <span v-if="node.data.emoji" class="mr-1">{{ node.data.emoji }}</span>
              <span v-else class="mr-1 text-surface-400">📄</span>
              {{ node.label }}
            </span>
            <Button
              icon="pi pi-ellipsis-h"
              aria-label="Actions de la note"
              severity="secondary"
              text
              rounded
              class="!w-8 !h-8 shrink-0 lg:opacity-0 transition-opacity note-tree-action"
              @click.stop="openNodeMenu($event, node)"
            />
          </div>
        </template>
      </Tree>
      <div v-else class="flex flex-col items-center justify-center py-8 text-center">
        <i class="pi pi-file-edit text-3xl text-surface-400 mb-3"></i>
        <p class="text-sm text-surface-500 dark:text-surface-400">Aucune note</p>
        <Button
          label="Créer une note"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          class="mt-3"
          @click="emit('create-root')"
        />
      </div>
    </div>
    <Menu ref="nodeMenu" :model="nodeMenuItems" :popup="true" />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Tree from 'primevue/tree'
import { computed, ref, watch } from 'vue'

const MAX_DEPTH = 3

const props = defineProps({
  nodes: { type: Array, default: () => [] },
  selectedKey: { type: String, default: null }
})

const emit = defineEmits(['select', 'create-root', 'create-child', 'delete'])

const expandedKeys = ref({})
const selectionKeys = ref({})
const nodeMenu = ref(null)
const nodeMenuItems = ref([])

function openNodeMenu(event, node) {
  const items = []

  if (canCreateChild(node.key)) {
    items.push({
      label: 'Ajouter une sous-note',
      icon: 'pi pi-plus',
      command: () => emit('create-child', node.key)
    })
  }

  items.push({
    label: 'Supprimer',
    icon: 'pi pi-trash',
    class: 'text-red-500',
    command: () => emit('delete', node.key)
  })

  nodeMenuItems.value = items
  nodeMenu.value.toggle(event)
}

const depthMap = computed(() => {
  const map = {}
  function walk(nodes, depth) {
    for (const node of nodes) {
      map[node.key] = depth
      if (node.children) {
        walk(node.children, depth + 1)
      }
    }
  }
  walk(props.nodes, 1)
  return map
})

function canCreateChild(nodeKey) {
  return (depthMap.value[nodeKey] || 1) < MAX_DEPTH
}

watch(
  () => props.selectedKey,
  (newKey) => {
    if (newKey) {
      selectionKeys.value = { [newKey]: true }
    } else {
      selectionKeys.value = {}
    }
  },
  { immediate: true }
)

function expandNode(key) {
  expandedKeys.value = { ...expandedKeys.value, [key]: true }
}

defineExpose({ expandNode })

function handleSelect(node) {
  emit('select', node.key)
}
</script>

<style>
.note-tree {
  overflow: hidden !important;
}

.note-tree * {
  max-width: 100%;
}

.note-tree .p-tree-node-label {
  min-width: 0 !important;
  overflow: hidden !important;
  flex: 1 !important;
}

.note-tree.p-tree {
  padding: 0 !important;
  gap: 0 !important;
}

.note-tree .p-tree-root-children {
  padding: 0 !important;
  gap: 2px !important;
}

.note-tree .p-tree-node {
  padding: 0 !important;
}

.note-tree .p-tree-node-content {
  padding: 0.2rem 0.25rem !important;
  gap: 0.25rem !important;
  border-radius: 0.375rem;
}

.note-tree .p-tree-node-children {
  padding-left: 0.75rem !important;
  gap: 2px !important;
}

.note-tree .p-tree-node-toggle-button {
  width: 1.25rem !important;
  height: 1.25rem !important;
}

.note-tree .p-tree-node-content:hover .note-tree-action {
  opacity: 1;
}
</style>
