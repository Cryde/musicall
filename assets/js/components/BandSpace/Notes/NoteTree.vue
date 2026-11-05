<template>
  <div class="flex flex-col h-full">
    <!-- On a phone the page's title and the floating « Nouvelle note » stand for this head. -->
    <div class="flex items-center justify-between p-3 border-b border-surface-200 dark:border-surface-700 max-md:hidden">
      <span class="font-semibold text-surface-700 dark:text-surface-200">{{ t('band_space_notes.tree.heading') }}</span>
      <Button
        v-tooltip.bottom="t('band_space_notes.tree.new_note')"
        :aria-label="t('band_space_notes.tree.new_note')"
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
              v-if="nodeActions(node).length > 0"
              icon="pi pi-ellipsis-h"
              :aria-label="t('band_space_notes.tree.note_actions')"
              severity="secondary"
              text
              rounded
              class="!w-8 !h-8 max-md:!w-11 max-md:!h-11 shrink-0 lg:opacity-0 transition-opacity note-tree-action"
              @click.stop="openNodeMenu($event, node)"
            />
          </div>
        </template>
      </Tree>
      <div v-else class="flex flex-col items-center justify-center py-8 text-center">
        <i class="pi pi-file-edit text-3xl text-surface-400 mb-3"></i>
        <p class="text-sm text-surface-500 dark:text-surface-400">{{ t('band_space_notes.tree.empty') }}</p>
        <Button
          :label="t('band_space_notes.tree.create_note')"
          icon="pi pi-plus"
          severity="secondary"
          size="small"
          class="mt-3"
          @click="emit('create-root')"
        />
      </div>
    </div>
    <Menu v-if="!isBelowMd" ref="nodeMenu" :model="nodeMenuItems" :popup="true" />

    <BottomSheet
      v-else
      v-model:visible="isActionsSheetOpen"
      :title="actionsSheetTitle"
      @after-hide="actionsSheet.onAfterHide"
    >
      <SheetAction
        v-for="item in nodeMenuItems"
        :key="item.label"
        :icon="item.sheetIcon"
        :danger="item.danger"
        @click="actionsSheet.run(item.command)"
      >
        {{ item.label }}
      </SheetAction>
    </BottomSheet>
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Menu from 'primevue/menu'
import Tree from 'primevue/tree'
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useActionAfterSheetHide } from '../../../composables/useActionAfterSheetHide.js'
import { useBandSpaceNavigation } from '../../../composables/useBandSpaceNavigation.js'
import { useIsBelowMd } from '../../../composables/useIsPhone.js'
import { useUserSecurityStore } from '../../../store/user/security.js'
import { canDeleteNote } from '../../../utils/noteActions.js'
import BottomSheet from '../../Global/BottomSheet.vue'
import SheetAction from '../../Global/SheetAction.vue'

const MAX_DEPTH = 3

const { t } = useI18n()

const props = defineProps({
  nodes: { type: Array, default: () => [] },
  selectedKey: { type: String, default: null }
})

const emit = defineEmits(['select', 'create-root', 'create-child', 'delete'])

const expandedKeys = ref({})
const selectionKeys = ref({})
const nodeMenu = ref(null)
const nodeMenuItems = ref([])

const userSecurityStore = useUserSecurityStore()
const { isAdmin } = useBandSpaceNavigation()

/**
 * Only what the API accepts: a note at the deepest level takes no child, and only its author or an
 * administrator deletes it (#1166). A note offering neither shows no menu at all.
 */
function nodeActions(node) {
  const items = []

  if (canCreateChild(node.key)) {
    items.push({
      label: t('band_space_notes.tree.add_child'),
      icon: 'pi pi-plus',
      sheetIcon: 'pi-plus',
      command: () => emit('create-child', node.key)
    })
  }

  if (canDeleteNote(node.data, userSecurityStore.userProfile?.id ?? null, isAdmin.value)) {
    items.push({
      label: t('band_space_notes.tree.delete'),
      icon: 'pi pi-trash',
      sheetIcon: 'pi-trash',
      danger: true,
      class: 'text-red-500',
      command: () => emit('delete', node.key)
    })
  }

  return items
}

function openNodeMenu(event, node) {
  nodeMenuItems.value = nodeActions(node)
  if (isBelowMd.value) {
    actionsSheetNode.value = node
    isActionsSheetOpen.value = true
    return
  }
  nodeMenu.value.toggle(event)
}

// On a phone ⋯ opens a sheet, named after the note it acts on.
const isBelowMd = useIsBelowMd()
const isActionsSheetOpen = ref(false)
const actionsSheetNode = ref(null)
// The sub-note dialog and the delete confirm open once the sheet has gone.
const actionsSheet = useActionAfterSheetHide(isActionsSheetOpen)
const actionsSheetTitle = computed(() => {
  const node = actionsSheetNode.value
  return node ? `${node.data.emoji || '📄'} ${node.label}` : ''
})

watch(isBelowMd, () => {
  isActionsSheetOpen.value = false
})

function findNode(nodes, key) {
  for (const node of nodes) {
    if (node.key === key) return node
    const inChildren = node.children ? findNode(node.children, key) : null
    if (inChildren) return inChildren
  }
  return null
}

/** Whether the note offers any action, so the open note's bar shows its ⋯ only when it does. */
function hasActions(noteId) {
  const node = findNode(props.nodes, noteId)
  return node !== null && nodeActions(node).length > 0
}

/** The same sheet, from the open note's bar rather than its row. */
function openActions(noteId, event) {
  const node = findNode(props.nodes, noteId)
  if (node) openNodeMenu(event, node)
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

function highlightOpenNote(noteId) {
  selectionKeys.value = noteId ? { [noteId]: true } : {}
}

watch(() => props.selectedKey, highlightOpenNote, { immediate: true })

function expandNode(key) {
  expandedKeys.value = { ...expandedKeys.value, [key]: true }
}

defineExpose({ expandNode, hasActions, openActions })

/**
 * Tree moves its own highlight before it tells us, and the parent may refuse the switch when the
 * open note holds text no save has taken. The highlight is therefore put back on whatever note is
 * actually open once the answer is in, so the sidebar never points at a note the editor did not
 * open. Idempotent when the switch went through.
 */
async function handleSelect(node) {
  emit('select', node.key)
  await nextTick()
  highlightOpenNote(props.selectedKey)
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

/* A phone: rows a finger can hit, the arrow to the sub-notes at 40px. */
@media (width < 48rem) {
  .note-tree .p-tree-node-content {
    min-height: 3rem;
    padding: 0.125rem 0.25rem !important;
  }

  .note-tree .p-tree-node-label .text-sm {
    font-size: 1rem;
    line-height: 1.5rem;
  }

  .note-tree .p-tree-node-toggle-button {
    width: 2.5rem !important;
    height: 2.5rem !important;
  }
}
</style>
