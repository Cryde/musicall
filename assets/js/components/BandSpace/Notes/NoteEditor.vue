<template>
  <div class="flex flex-col h-full">
    <!-- Title -->
    <div class="p-4 border-b border-surface-200 dark:border-surface-700">
      <div class="flex items-center justify-between">
        <div class="flex items-center flex-1 min-w-0">
          <div ref="emojiPickerRef" class="relative">
            <button
              class="text-2xl w-10 h-10 flex items-center justify-center rounded-lg hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors cursor-pointer"
              @click="showEmojiPicker = !showEmojiPicker"
            >
              {{ note.emoji || '📄' }}
            </button>
            <div v-if="showEmojiPicker" class="absolute top-12 left-0 z-50">
              <EmojiPicker
                :native="true"
                :disable-skin-tones="true"
                theme="auto"
                @select="handleEmojiSelect"
              />
            </div>
          </div>
          <InputText
            v-model="editableTitle"
            class="w-full text-xl font-semibold border-0 shadow-none bg-transparent"
            placeholder="Titre de la note"
            @blur="handleTitleBlur"
            @keydown.enter="$event.target.blur()"
          />
        </div>
        <span v-if="saveStatus" class="text-xs text-surface-500 whitespace-nowrap ml-3">
          <template v-if="saveStatus === 'saving'">
            <i class="pi pi-spin pi-spinner mr-1"></i>Sauvegarde en cours...
          </template>
          <template v-else-if="saveStatus === 'saved'">
            <i class="pi pi-check mr-1 text-green-500"></i>Sauvegardé
          </template>
          <template v-else-if="saveStatus === 'error'">
            <i class="pi pi-times mr-1 text-red-500"></i>Erreur
          </template>
        </span>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="sticky top-0 z-30 bg-surface-50 dark:bg-surface-700 border-b border-surface-200 dark:border-surface-600 p-2">
      <div class="flex flex-wrap items-center gap-1">
        <!-- Text formatting -->
        <div class="flex items-center gap-1 pr-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="'Gras'"
            :severity="editor?.isActive('bold') ? 'primary' : 'secondary'"
            text
            size="small"
            class="font-bold"
            @click="editor?.chain().focus().toggleBold().run()"
          >
            B
          </Button>
          <Button
            v-tooltip.bottom="'Italique'"
            :severity="editor?.isActive('italic') ? 'primary' : 'secondary'"
            text
            size="small"
            class="italic"
            @click="editor?.chain().focus().toggleItalic().run()"
          >
            I
          </Button>
        </div>

        <!-- Headings -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="'Titre 2'"
            label="H2"
            :severity="editor?.isActive('heading', { level: 2 }) ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
          />
          <Button
            v-tooltip.bottom="'Titre 3'"
            label="H3"
            :severity="editor?.isActive('heading', { level: 3 }) ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
          />
        </div>

        <!-- Lists -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="'Liste à puces'"
            icon="pi pi-list"
            :severity="editor?.isActive('bulletList') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleBulletList().run()"
          />
          <Button
            v-tooltip.bottom="'Liste numérotée'"
            icon="pi pi-list-check"
            :severity="editor?.isActive('orderedList') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleOrderedList().run()"
          />
        </div>

        <!-- Blocks -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="'Citation'"
            icon="pi pi-comment"
            :severity="editor?.isActive('blockquote') ? 'primary' : 'secondary'"
            text
            size="small"
            @click="editor?.chain().focus().toggleBlockquote().run()"
          />
          <Button
            v-tooltip.bottom="'Ligne horizontale'"
            icon="pi pi-minus"
            severity="secondary"
            text
            size="small"
            @click="editor?.chain().focus().setHorizontalRule().run()"
          />
        </div>

        <!-- Table -->
        <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
          <Button
            v-tooltip.bottom="'Insérer un tableau'"
            icon="pi pi-table"
            severity="secondary"
            text
            size="small"
            @click="editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: false }).run()"
          />
        </div>

        <!-- Undo/Redo -->
        <div class="flex items-center gap-1 px-2">
          <Button
            v-tooltip.bottom="'Annuler'"
            icon="pi pi-undo"
            severity="secondary"
            text
            size="small"
            :disabled="!editor?.can().undo()"
            @click="editor?.chain().focus().undo().run()"
          />
          <Button
            v-tooltip.bottom="'Rétablir'"
            icon="pi pi-refresh"
            severity="secondary"
            text
            size="small"
            :disabled="!editor?.can().redo()"
            @click="editor?.chain().focus().redo().run()"
          />
        </div>
      </div>
    </div>

    <!-- Table bubble menu -->
    <BubbleMenu
      v-if="editor"
      :editor="editor"
      :tippy-options="{ duration: 150, placement: 'top' }"
      :should-show="shouldShowTableMenu"
    >
      <div class="flex items-center gap-0.5 bg-surface-0 dark:bg-surface-800 border border-surface-200 dark:border-surface-600 rounded-lg shadow-lg p-1">
        <Button
          v-tooltip.top="'Colonne avant'"
          icon="pi pi-chevron-left"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addColumnBefore().run()"
        />
        <Button
          v-tooltip.top="'Colonne après'"
          icon="pi pi-chevron-right"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addColumnAfter().run()"
        />
        <Button
          v-tooltip.top="'Supprimer colonne'"
          icon="pi pi-minus"
          severity="danger"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().deleteColumn().run()"
        />
        <div class="w-px h-5 bg-surface-200 dark:bg-surface-600 mx-0.5" />
        <Button
          v-tooltip.top="'Ligne avant'"
          icon="pi pi-chevron-up"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addRowBefore().run()"
        />
        <Button
          v-tooltip.top="'Ligne après'"
          icon="pi pi-chevron-down"
          severity="secondary"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().addRowAfter().run()"
        />
        <Button
          v-tooltip.top="'Supprimer ligne'"
          icon="pi pi-minus-circle"
          severity="danger"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().deleteRow().run()"
        />
        <div class="w-px h-5 bg-surface-200 dark:bg-surface-600 mx-0.5" />
        <Button
          v-tooltip.top="'Supprimer tableau'"
          icon="pi pi-trash"
          severity="danger"
          text
          size="small"
          class="!w-7 !h-7"
          @click="editor?.chain().focus().deleteTable().run()"
        />
      </div>
    </BubbleMenu>

    <!-- Editor content -->
    <div class="flex-1 p-4 overflow-y-auto">
      <EditorContent :editor="editor" class="note-editor" />
    </div>
  </div>
</template>

<script setup>
import Placeholder from '@tiptap/extension-placeholder'
import { TableCell } from '@tiptap/extension-table/cell'
import { TableRow } from '@tiptap/extension-table/row'
import { Table } from '@tiptap/extension-table/table'
import TextAlign from '@tiptap/extension-text-align'
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import { BubbleMenu } from '@tiptap/vue-3/menus'
import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import EmojiPicker from 'vue3-emoji-picker'
import 'vue3-emoji-picker/css'
import { onClickOutside } from '@vueuse/core'
import { onBeforeUnmount, ref } from 'vue'

const props = defineProps({
  note: { type: Object, required: true },
  saveStatus: { type: String, default: null }
})

const emit = defineEmits(['update-content', 'update-title', 'update-emoji'])

const showEmojiPicker = ref(false)
const emojiPickerRef = ref(null)

onClickOutside(emojiPickerRef, () => {
  showEmojiPicker.value = false
})

const editableTitle = ref(props.note.title)

let saveTimeout = null
let pendingContent = null

function debouncedSave(json) {
  cancelDebouncedSave()
  pendingContent = json
  saveTimeout = setTimeout(() => {
    emit('update-content', { noteId: props.note.id, content: json })
    pendingContent = null
  }, 2000)
}

function cancelDebouncedSave() {
  if (saveTimeout) {
    clearTimeout(saveTimeout)
    saveTimeout = null
  }
}

function flushPendingSave() {
  if (pendingContent) {
    cancelDebouncedSave()
    emit('update-content', { noteId: props.note.id, content: pendingContent })
    pendingContent = null
  }
}

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: { levels: [2, 3] }
    }),
    TextAlign.configure({
      types: ['heading', 'paragraph']
    }),
    Placeholder.configure({
      placeholder: 'Commencez à écrire...'
    }),
    Table.configure({ resizable: true }),
    TableRow.extend({ content: 'tableCell*' }),
    TableCell
  ],
  content: props.note.content || '',
  onUpdate: ({ editor }) => {
    debouncedSave(editor.getJSON())
  }
})

onBeforeUnmount(() => {
  flushPendingSave()
  editor.value?.destroy()
})

function shouldShowTableMenu({ editor }) {
  return editor.isActive('table')
}

function handleEmojiSelect(emoji) {
  showEmojiPicker.value = false
  emit('update-emoji', emoji.i)
}

function handleTitleBlur() {
  const trimmed = editableTitle.value.trim()
  if (trimmed && trimmed !== props.note.title) {
    emit('update-title', trimmed)
  }
}
</script>

<style>
.note-editor .tiptap {
  min-height: 300px;
  outline: none;
}

.note-editor .tiptap h2 {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 1.25rem 0 0.5rem;
  line-height: 1.3;
}

.note-editor .tiptap h3 {
  font-size: 1.25rem;
  font-weight: 600;
  margin: 1rem 0 0.5rem;
  line-height: 1.4;
}

.note-editor .tiptap p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: var(--p-surface-400);
  pointer-events: none;
  height: 0;
}

.note-editor .tiptap ul {
  padding-left: 1.5rem;
  margin-bottom: 0.75rem;
  list-style-type: disc;
}

.note-editor .tiptap ol {
  padding-left: 1.5rem;
  margin-bottom: 0.75rem;
  list-style-type: decimal;
}

.note-editor .tiptap li {
  margin-bottom: 0.25rem;
}

.note-editor .tiptap li p {
  margin: 0;
}

.note-editor .tiptap blockquote {
  border-left: 4px solid var(--p-surface-300);
  padding-left: 1rem;
  margin-left: 0;
  margin-right: 0;
  font-style: italic;
  color: var(--p-surface-500);
}

.note-editor .tiptap hr {
  border: none;
  border-top: 2px solid var(--p-surface-300);
  margin: 1.5rem 0;
}

.note-editor .tiptap table {
  border-collapse: collapse;
  width: 100%;
  margin: 1rem 0;
  overflow: hidden;
}

.note-editor .tiptap table td {
  border: 1px solid var(--p-surface-300);
  padding: 0.5rem 0.75rem;
  position: relative;
  min-width: 80px;
  vertical-align: top;
}

.note-editor .tiptap table .selectedCell::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(59, 130, 246, 0.1);
  pointer-events: none;
}

.note-editor .tiptap table .column-resize-handle {
  position: absolute;
  right: -2px;
  top: 0;
  bottom: -2px;
  width: 4px;
  background-color: #3b82f6;
  pointer-events: none;
}

</style>
