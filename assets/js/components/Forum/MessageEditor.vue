<template>
  <div>
    <div v-if="editor" class="border border-surface-200 dark:border-surface-700 rounded-lg overflow-hidden">
      <div class="flex flex-wrap gap-1 p-2 bg-surface-50 dark:bg-surface-800 border-b border-surface-200 dark:border-surface-700">
        <Button
          v-tooltip.bottom="'Gras'"
          :severity="editor.isActive('bold') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleBold().run()"
        >
          <span class="font-bold text-base">B</span>
        </Button>
        <Button
          v-tooltip.bottom="'Italique'"
          :severity="editor.isActive('italic') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleItalic().run()"
        >
          <span class="italic text-base">I</span>
        </Button>
        <Divider layout="vertical" class="mx-1" />
        <Button
          v-tooltip.bottom="'Liste à puces'"
          icon="pi pi-list"
          :severity="editor.isActive('bulletList') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleBulletList().run()"
        />
        <Button
          v-tooltip.bottom="'Liste numérotée'"
          :severity="editor.isActive('orderedList') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleOrderedList().run()"
        >
          <i class="pi pi-list" />
        </Button>
        <Divider layout="vertical" class="mx-1" />
        <Button
          v-tooltip.bottom="'Citation'"
          icon="pi pi-comment"
          :severity="editor.isActive('blockquote') ? 'info' : 'secondary'"
          text
          size="small"
          @click="editor.chain().focus().toggleBlockquote().run()"
        />
        <Divider layout="vertical" class="mx-1" />
        <Button
          v-tooltip.bottom="'Annuler'"
          icon="pi pi-undo"
          severity="secondary"
          text
          size="small"
          :disabled="!editor.can().undo()"
          @click="editor.chain().focus().undo().run()"
        />
        <Button
          v-tooltip.bottom="'Rétablir'"
          icon="pi pi-refresh"
          severity="secondary"
          text
          size="small"
          :disabled="!editor.can().redo()"
          @click="editor.chain().focus().redo().run()"
        />
      </div>

      <EditorContent :editor="editor" class="p-3 min-h-[150px] prose dark:prose-invert max-w-none" />
    </div>
  </div>
</template>

<script setup>
import StarterKit from '@tiptap/starter-kit'
import { EditorContent, useEditor } from '@tiptap/vue-3'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import { watch } from 'vue'

const props = defineProps({
  previousContent: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['content-update'])

const editor = useEditor({
  extensions: [
    StarterKit.configure({
      heading: false
    })
  ],
  content: props.previousContent,
  onUpdate: ({ editor }) => {
    emit('content-update', {
      html: editor.getHTML(),
      text: editor.getText()
    })
  }
})

watch(
  () => props.previousContent,
  (newContent) => {
    if (editor.value && newContent !== editor.value.getHTML()) {
      editor.value.commands.setContent(newContent)
    }
  }
)

function reset() {
  editor.value?.commands.clearContent(true)
}

defineExpose({ reset })
</script>

<style>
.ProseMirror {
  outline: none;
  min-height: 100px;
}

.ProseMirror p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: #adb5bd;
  pointer-events: none;
  height: 0;
}
</style>
