import Placeholder from '@tiptap/extension-placeholder'
import { TableCell } from '@tiptap/extension-table/cell'
import { TableRow } from '@tiptap/extension-table/row'
import { Table } from '@tiptap/extension-table/table'
import TextAlign from '@tiptap/extension-text-align'
import StarterKit from '@tiptap/starter-kit'
import { useEditor } from '@tiptap/vue-3'
import { onBeforeUnmount, ref } from 'vue'
import { createDebouncedSaver } from '../utils/debouncedSaver.js'

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
 * `stopSaving` is the escape hatch for a save the server refuses for good, a note whose
 * body moved on under the writer being the case that exists today. It ends the loop rather
 * than letting it re-send the same refused document every couple of seconds.
 *
 * `hasPendingEdits` is what the unsaved changes guards read. The flush on unmount covers
 * navigating away inside the app, but nothing covers a closed tab or an F5, so the view has to
 * be able to ask whether there is typing in here that no save has been started for.
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
  // True from the first keystroke until the debounce hands the text to a save. What happens to that
  // save afterwards is the consumer's business; this only answers "is there typing no save has been
  // started for", which is what a tab closed mid sentence would take with it.
  const hasPendingEdits = ref(false)

  const saver = createDebouncedSaver({
    delayMs: debounceMs,
    onSave: (content) => {
      hasPendingEdits.value = false

      return onSave(content)
    }
  })

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
      // Set even when the loop has been stopped: a refused save leaves the text on screen, and it
      // is unsaved whether or not anything is still trying.
      hasPendingEdits.value = true
      saver.schedule(editor.getJSON())
    }
  })

  onBeforeUnmount(() => {
    saver.flush()
    editor.value?.destroy()
  })

  return {
    editor,
    hasPendingEdits,
    flushPendingSave: saver.flush,
    cancelDebouncedSave: saver.cancel,
    stopSaving: saver.stop
  }
}
