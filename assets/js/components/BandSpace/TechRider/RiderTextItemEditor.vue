<template>
  <div class="rider-text-item-editor">
    <div
      v-if="!readOnly"
      class="flex flex-wrap items-center gap-1 border-b border-surface-200 dark:border-surface-700 pb-2 mb-2"
    >
      <div class="flex items-center gap-1 pr-2 border-r border-surface-200 dark:border-surface-700">
        <Button
          v-tooltip.bottom="'Gras'"
          aria-label="Gras"
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
          aria-label="Italique"
          :severity="editor?.isActive('italic') ? 'primary' : 'secondary'"
          text
          size="small"
          class="italic"
          @click="editor?.chain().focus().toggleItalic().run()"
        >
          I
        </Button>
        <Button
          v-tooltip.bottom="'Souligné'"
          aria-label="Souligné"
          :severity="editor?.isActive('underline') ? 'primary' : 'secondary'"
          text
          size="small"
          class="underline"
          @click="editor?.chain().focus().toggleUnderline().run()"
        >
          U
        </Button>
      </div>

      <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
        <button
          v-for="colour in colours"
          :key="colour.value"
          type="button"
          class="w-6 h-6 rounded border border-surface-300 dark:border-surface-600 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-primary-500"
          :style="{ backgroundColor: colour.hex }"
          :aria-label="`Texte en ${colour.label.toLowerCase()}`"
          :aria-pressed="editor?.isActive('textStyle', { color: colour.hex }) ?? false"
          v-tooltip.bottom="colour.label"
          @click="editor?.chain().focus().setColor(colour.hex).run()"
        />
        <Button
          v-tooltip.bottom="'Retirer la couleur'"
          aria-label="Retirer la couleur"
          icon="pi pi-ban"
          severity="secondary"
          text
          size="small"
          @click="editor?.chain().focus().unsetColor().run()"
        />
      </div>

      <div class="flex items-center gap-1 px-2 border-r border-surface-200 dark:border-surface-700">
        <Button
          v-tooltip.bottom="'Liste à puces'"
          aria-label="Liste à puces"
          icon="pi pi-list"
          :severity="editor?.isActive('bulletList') ? 'primary' : 'secondary'"
          text
          size="small"
          @click="editor?.chain().focus().toggleBulletList().run()"
        />
        <Button
          v-tooltip.bottom="'Liste numérotée'"
          aria-label="Liste numérotée"
          icon="pi pi-list-check"
          :severity="editor?.isActive('orderedList') ? 'primary' : 'secondary'"
          text
          size="small"
          @click="editor?.chain().focus().toggleOrderedList().run()"
        />
        <Button
          v-tooltip.bottom="'Insérer un tableau'"
          aria-label="Insérer un tableau"
          icon="pi pi-table"
          severity="secondary"
          text
          size="small"
          @click="editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: false }).run()"
        />
      </div>

      <div class="flex items-center gap-1 px-2">
        <Button
          v-tooltip.bottom="'Annuler'"
          aria-label="Annuler"
          icon="pi pi-undo"
          severity="secondary"
          text
          size="small"
          :disabled="!editor?.can().undo()"
          @click="editor?.chain().focus().undo().run()"
        />
        <Button
          v-tooltip.bottom="'Rétablir'"
          aria-label="Rétablir"
          icon="pi pi-refresh"
          severity="secondary"
          text
          size="small"
          :disabled="!editor?.can().redo()"
          @click="editor?.chain().focus().redo().run()"
        />
      </div>
    </div>

    <EditorContent :editor="editor" :aria-label="`Contenu de l'élément ${title}`" />
  </div>
</template>

<script setup>
import { Color, TextStyle } from '@tiptap/extension-text-style'
import { EditorContent } from '@tiptap/vue-3'
import Button from 'primevue/button'
import { watch } from 'vue'
import { useRichTextEditor } from '../../../composables/useRichTextEditor.js'
import { TECH_RIDER_COLOUR_HEXES, TECH_RIDER_COLOURS } from '../../../constants/techRiderColours.js'

const props = defineProps({
  itemId: { type: String, required: true },
  title: { type: String, required: true },
  content: { type: Object, default: null },
  readOnly: { type: Boolean, default: false }
})

const emit = defineEmits(['save'])

const colours = TECH_RIDER_COLOURS

// Color is restricted to the palette so the exported document only ever carries values the
// renderer knows. Without the allowlist a paste from Word would smuggle in arbitrary CSS.
const { editor } = useRichTextEditor({
  content: props.content,
  placeholder: 'Décrivez vos besoins pour cet élément...',
  editable: !props.readOnly,
  extensions: [TextStyle, Color.configure({ types: ['textStyle'] })],
  onSave: (json) => emit('save', { itemId: props.itemId, content: sanitiseColours(json) })
})

watch(
  () => props.readOnly,
  (readOnly) => editor.value?.setEditable(!readOnly)
)

/**
 * Strips any colour that is not in the palette. The toolbar can only apply allowed ones, but
 * pasted content carries whatever the source used, and that would reach the export.
 */
function sanitiseColours(node) {
  if (!node || typeof node !== 'object') return node

  const cleaned = { ...node }

  if (Array.isArray(cleaned.marks)) {
    cleaned.marks = cleaned.marks
      .map((mark) => {
        if (mark?.type !== 'textStyle' || !mark.attrs?.color) return mark
        // Lowercased before matching: TipTap keeps a pasted style attribute verbatim, so
        // `#DC2626` from an external document is the palette's Rouge and must survive.
        if (TECH_RIDER_COLOUR_HEXES.includes(String(mark.attrs.color).toLowerCase())) return mark
        const { color, ...rest } = mark.attrs
        return Object.keys(rest).length > 0 ? { ...mark, attrs: rest } : null
      })
      .filter(Boolean)
    if (cleaned.marks.length === 0) delete cleaned.marks
  }

  if (Array.isArray(cleaned.content)) {
    cleaned.content = cleaned.content.map(sanitiseColours)
  }

  return cleaned
}
</script>

<style>
.rider-text-item-editor .tiptap {
  min-height: 8rem;
  outline: none;
}

.rider-text-item-editor .tiptap p.is-editor-empty:first-child::before {
  content: attr(data-placeholder);
  float: left;
  color: var(--p-surface-400);
  pointer-events: none;
  height: 0;
}

.rider-text-item-editor .tiptap ul {
  padding-left: 1.5rem;
  margin-bottom: 0.75rem;
  list-style-type: disc;
}

.rider-text-item-editor .tiptap ol {
  padding-left: 1.5rem;
  margin-bottom: 0.75rem;
  list-style-type: decimal;
}

.rider-text-item-editor .tiptap li {
  margin-bottom: 0.25rem;
}

.rider-text-item-editor .tiptap li p {
  margin: 0;
}

.rider-text-item-editor .tiptap table {
  border-collapse: collapse;
  width: 100%;
  margin: 1rem 0;
}

.rider-text-item-editor .tiptap table td {
  border: 1px solid var(--p-surface-300);
  padding: 0.5rem 0.75rem;
  min-width: 80px;
  vertical-align: top;
}
</style>
