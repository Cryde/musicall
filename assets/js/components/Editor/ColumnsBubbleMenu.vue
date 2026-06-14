<template>
  <div
    ref="rootEl"
    class="columns-bubble-menu items-center gap-1 rounded-md border border-surface-200 bg-surface-0 p-1 shadow-md dark:border-surface-700 dark:bg-surface-800"
    style="visibility: hidden; opacity: 0"
  >
    <Button
      v-tooltip.top="'2 colonnes'"
      label="2"
      :severity="currentCols === 2 ? 'primary' : 'secondary'"
      text
      size="small"
      @click="handleSetCount(2)"
    />
    <Button
      v-tooltip.top="'3 colonnes'"
      label="3"
      :severity="currentCols === 3 ? 'primary' : 'secondary'"
      text
      size="small"
      @click="handleSetCount(3)"
    />
    <Divider layout="vertical" class="mx-1 my-0" />
    <Button
      v-tooltip.top="'Supprimer le bloc colonnes'"
      aria-label="Supprimer le bloc colonnes"
      icon="pi pi-trash"
      severity="danger"
      text
      size="small"
      @click="handleDelete"
    />
  </div>
</template>

<script setup>
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import { useConfirm } from 'primevue/useconfirm'
import { onBeforeUnmount, ref, watch } from 'vue'

const props = defineProps({
  editor: {
    type: Object,
    default: null
  }
})

const rootEl = ref(null)
const currentCols = ref(null)
const confirm = useConfirm()

let attachedEditor = null

function syncCurrentCols() {
  currentCols.value = props.editor?.getAttributes('columns')?.cols ?? null
}

function attachListeners(editor) {
  if (!editor || attachedEditor === editor) return
  detachListeners()
  editor.on('selectionUpdate', syncCurrentCols)
  editor.on('transaction', syncCurrentCols)
  syncCurrentCols()
  attachedEditor = editor
}

function detachListeners() {
  if (!attachedEditor) return
  try {
    attachedEditor.off('selectionUpdate', syncCurrentCols)
    attachedEditor.off('transaction', syncCurrentCols)
  } catch (_) {
    // editor may already be destroyed
  }
  attachedEditor = null
}

watch(
  () => props.editor,
  (editor) => {
    detachListeners()
    if (editor) attachListeners(editor)
  },
  { immediate: true }
)

onBeforeUnmount(detachListeners)

defineExpose({ rootEl })

function handleSetCount(target) {
  if (!props.editor) return
  if (currentCols.value === target) return

  if (currentCols.value === 3 && target === 2) {
    confirm.require({
      message: 'Le contenu de la 3e colonne sera perdu. Continuer ?',
      header: 'Réduire à 2 colonnes',
      icon: 'pi pi-exclamation-triangle',
      rejectLabel: 'Annuler',
      acceptLabel: 'Continuer',
      acceptClass: 'p-button-danger',
      accept: () => {
        props.editor.chain().focus().setColumnsCount(target).run()
      }
    })
    return
  }

  props.editor.chain().focus().setColumnsCount(target).run()
}

function handleDelete() {
  if (!props.editor) return
  props.editor.chain().focus().deleteColumns().run()
}
</script>

<style scoped>
.columns-bubble-menu {
  display: flex;
  z-index: 50;
}
</style>
