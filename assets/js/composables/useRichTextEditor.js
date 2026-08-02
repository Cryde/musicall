import Placeholder from '@tiptap/extension-placeholder'
import { TableCell } from '@tiptap/extension-table/cell'
import { TableRow } from '@tiptap/extension-table/row'
import { Table } from '@tiptap/extension-table/table'
import TextAlign from '@tiptap/extension-text-align'
import StarterKit from '@tiptap/starter-kit'
import { useEditor } from '@tiptap/vue-3'
import { onBeforeUnmount } from 'vue'

/**
 * The TipTap setup shared by every rich text surface in the app: Notes and tech rider
 * sections today.
 *
 * It exists so a fix to the editor is made once. The two consumers differ only in which
 * extensions they add on top (Notes uploads images, rider sections colour text from a fixed
 * palette) and in what they do with a save, so those are the parameters.
 *
 * Saving is debounced rather than explicit because this is prose: a save button on a
 * paragraph someone is still writing is friction. The flush on unmount is the important
 * half, without it the last few seconds of typing are lost by navigating away.
 *
 * @param {object}   options
 * @param {object|string|null} options.content        initial TipTap document
 * @param {string}   options.placeholder
 * @param {Array}    [options.extensions]             consumer specific extensions
 * @param {number}   [options.debounceMs]
 * @param {boolean}  [options.editable]
 * @param {function} options.onSave                   receives the document as JSON
 */
export function useRichTextEditor({
  content,
  placeholder,
  extensions = [],
  debounceMs = 2000,
  editable = true,
  onSave
}) {
  let saveTimeout = null
  let pendingContent = null

  function cancelDebouncedSave() {
    if (saveTimeout) {
      clearTimeout(saveTimeout)
      saveTimeout = null
    }
  }

  function debouncedSave(json) {
    cancelDebouncedSave()
    pendingContent = json
    saveTimeout = setTimeout(() => {
      pendingContent = null
      onSave(json)
    }, debounceMs)
  }

  /** Writes anything still waiting on the debounce. Safe to call when nothing is pending. */
  function flushPendingSave() {
    if (pendingContent === null) return
    const json = pendingContent
    pendingContent = null
    cancelDebouncedSave()
    onSave(json)
  }

  const editor = useEditor({
    editable,
    extensions: [
      StarterKit.configure({
        heading: { levels: [2, 3] }
      }),
      TextAlign.configure({
        types: ['heading', 'paragraph']
      }),
      Placeholder.configure({ placeholder }),
      Table.configure({ resizable: true }),
      TableRow.extend({ content: 'tableCell*' }),
      TableCell,
      ...extensions
    ],
    content: content || '',
    onUpdate: ({ editor }) => {
      debouncedSave(editor.getJSON())
    }
  })

  onBeforeUnmount(() => {
    flushPendingSave()
    editor.value?.destroy()
  })

  return { editor, flushPendingSave, cancelDebouncedSave }
}
