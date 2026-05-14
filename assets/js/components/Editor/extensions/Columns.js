import { mergeAttributes, Node } from '@tiptap/core'

const ALLOWED_COUNTS = [2, 3]
const DEFAULT_COUNT = 2

function normalizeCount(value) {
  const n = Number(value)
  return ALLOWED_COUNTS.includes(n) ? n : DEFAULT_COUNT
}

function findColumnsAncestor($pos, columnsNodeName) {
  for (let depth = $pos.depth; depth >= 0; depth -= 1) {
    if ($pos.node(depth).type.name === columnsNodeName) {
      return {
        node: $pos.node(depth),
        pos: $pos.before(depth),
        end: $pos.after(depth)
      }
    }
  }
  return null
}

export const Column = Node.create({
  name: 'column',
  content:
    '(paragraph | heading | bulletList | orderedList | blockquote | codeBlock | horizontalRule | image | youtube)+',
  isolating: true,

  parseHTML() {
    return [{ tag: 'div[data-type="column"]' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'column' }), 0]
  }
})

export const Columns = Node.create({
  name: 'columns',
  group: 'block',
  content: 'column{2,3}',
  isolating: true,
  defining: true,

  addAttributes() {
    return {
      cols: {
        default: DEFAULT_COUNT,
        parseHTML: (element) => normalizeCount(element.getAttribute('data-cols')),
        renderHTML: (attrs) => ({ 'data-cols': normalizeCount(attrs.cols) })
      }
    }
  },

  parseHTML() {
    return [{ tag: 'div[data-type="columns"]' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', mergeAttributes(HTMLAttributes, { 'data-type': 'columns' }), 0]
  },

  addCommands() {
    return {
      insertColumns:
        (cols = DEFAULT_COUNT) =>
        ({ commands }) => {
          const count = normalizeCount(cols)
          return commands.insertContent({
            type: this.name,
            attrs: { cols: count },
            content: Array.from({ length: count }, () => ({
              type: 'column',
              content: [{ type: 'paragraph' }]
            }))
          })
        },

      setColumnsCount:
        (cols) =>
        ({ state, dispatch, tr }) => {
          const target = normalizeCount(cols)
          const found = findColumnsAncestor(state.selection.$from, this.name)
          if (!found) return false
          if (found.node.attrs.cols === target) return false

          const columnType = state.schema.nodes.column
          const paragraphType = state.schema.nodes.paragraph
          const existing = []
          found.node.forEach((child) => {
            existing.push(child)
          })

          const newChildren = []
          for (let i = 0; i < target; i += 1) {
            newChildren.push(existing[i] ?? columnType.create(null, paragraphType.create()))
          }

          if (dispatch) {
            const newNode = this.type.create({ cols: target }, newChildren)
            tr.replaceWith(found.pos, found.pos + found.node.nodeSize, newNode)
          }
          return true
        },

      deleteColumns:
        () =>
        ({ state, dispatch, tr }) => {
          const found = findColumnsAncestor(state.selection.$from, this.name)
          if (!found) return false
          if (dispatch) {
            tr.delete(found.pos, found.pos + found.node.nodeSize)
          }
          return true
        }
    }
  },

  addKeyboardShortcuts() {
    return {
      'Mod-Enter': () => {
        if (!this.editor.isActive(this.name)) return false
        const found = findColumnsAncestor(this.editor.state.selection.$from, this.name)
        if (!found) return false

        return this.editor
          .chain()
          .insertContentAt(found.end, { type: 'paragraph' })
          .focus(found.end + 1)
          .run()
      }
    }
  }
})
