import { history, redo, undo } from '@tiptap/pm/history'
import { Plugin, PluginKey } from '@tiptap/pm/state'
import { Decoration, DecorationSet } from '@tiptap/pm/view'
import { Extension, Mark, mergeAttributes, Node, VueNodeViewRenderer } from '@tiptap/vue-3'
import { singerColors, singerTint } from '../../../../utils/chordpro.js'
import { NODE, SINGER_MARK } from '../../../../utils/chordproTiptap.js'
import LyricsSectionView from './LyricsSectionView.vue'

/**
 * The simple mode's schema (#1055). Nothing here knows ChordPro: chordproTiptap.js converts both
 * ways, so the text stays the one source of truth.
 */

const LEAF = `(${NODE.line}|${NODE.comment}|${NODE.directive})`

const LyricsDocument = Node.create({
  name: 'doc',
  topNode: true,
  content: `(${NODE.section}|${LEAF})+`
})

const Text = Node.create({ name: 'text', group: 'inline' })

const History = Extension.create({
  name: 'lyricsHistory',
  addProseMirrorPlugins: () => [history()],
  addKeyboardShortcuts() {
    const run = (command) => () => command(this.editor.state, this.editor.view.dispatch)
    return { 'Mod-z': run(undo), 'Shift-Mod-z': run(redo), 'Mod-y': run(redo) }
  }
})

const LyricsLine = Node.create({
  name: NODE.line,
  group: 'block',
  content: 'inline*',
  parseHTML: () => [{ tag: 'p' }],
  renderHTML: ({ HTMLAttributes }) => [
    'p',
    mergeAttributes(HTMLAttributes, { class: 'lyrics-line' }),
    0
  ]
})

const LyricsComment = Node.create({
  name: NODE.comment,
  group: 'block',
  content: 'text*',
  marks: '',
  parseHTML: () => [{ tag: 'p.lyrics-comment' }],
  renderHTML: ({ HTMLAttributes }) => [
    'p',
    mergeAttributes(HTMLAttributes, { class: 'lyrics-comment' }),
    0
  ]
})

/** A directive this app does not act on (`{title}`, `{capo}`): kept verbatim, shown greyed, only deletable. */
const LyricsDirective = Node.create({
  name: NODE.directive,
  group: 'block',
  atom: true,
  selectable: true,
  addAttributes: () => ({ raw: { default: '' } }),
  parseHTML: () => [{ tag: 'p.lyrics-directive' }],
  renderHTML: ({ node }) => [
    'p',
    { class: 'lyrics-directive', contenteditable: 'false' },
    node.attrs.raw
  ]
})

const LyricsSection = Node.create({
  name: NODE.section,
  group: 'block',
  content: `${LEAF}+`,
  defining: true,
  addAttributes: () => ({ kind: { default: 'verse' }, label: { default: '' } }),
  parseHTML: () => [{ tag: 'section.lyrics-section' }],
  renderHTML: ({ HTMLAttributes }) => [
    'section',
    mergeAttributes(HTMLAttributes, { class: 'lyrics-section' }),
    0
  ],
  addNodeView: () => VueNodeViewRenderer(LyricsSectionView)
})

/** Drawn over the syllable it sits before, by CSS, so it takes no room in the line. */
const Chord = Node.create({
  name: NODE.chord,
  group: 'inline',
  inline: true,
  atom: true,
  selectable: true,
  marks: '',
  addAttributes: () => ({ name: { default: '' } }),
  parseHTML: () => [{ tag: 'span.lyrics-chord' }],
  renderHTML: ({ node }) => ['span', { class: 'lyrics-chord', 'data-chord': node.attrs.name }]
})

const Singer = Mark.create({
  name: SINGER_MARK,
  inclusive: false,
  addAttributes: () => ({
    singers: {
      default: [],
      parseHTML: (element) =>
        (element.getAttribute('data-singers') ?? '').split(' ').filter(Boolean),
      renderHTML: (attributes) => ({ 'data-singers': attributes.singers.join(' ') })
    }
  }),
  parseHTML: () => [{ tag: 'span[data-singers]' }],
  renderHTML: ({ HTMLAttributes }) => ['span', HTMLAttributes, 0]
})

function marginTags(sets, colors, nameOf) {
  const gutter = document.createElement('span')
  gutter.className = 'lyrics-gutter'
  gutter.contentEditable = 'false'
  for (const id of sets.flat()) {
    const tag = document.createElement('span')
    tag.className = 'lyrics-singer-tag'
    tag.style.background = colors[id]
    tag.textContent = nameOf(id)
    gutter.appendChild(tag)
  }
  return gutter
}

/**
 * The colours and the names in the margin, drawn as decorations rather than stored: they depend on
 * the whole song (a colour is the order a singer first appears), which a mark alone cannot see.
 */
function singerDecorations(doc, nameOf) {
  const order = []
  doc.descendants((node) => {
    for (const id of node.marks.find((mark) => mark.type.name === SINGER_MARK)?.attrs.singers ??
      []) {
      if (!order.includes(id)) order.push(id)
    }
  })
  const colors = singerColors(order)
  const decorations = []

  doc.descendants((node, pos) => {
    if (node.type.name !== NODE.line) return true
    const sets = []
    node.forEach((child, offset) => {
      const singers = child.marks.find((mark) => mark.type.name === SINGER_MARK)?.attrs.singers
      if (!singers) return
      const from = pos + 1 + offset
      decorations.push(
        Decoration.inline(from, from + child.nodeSize, {
          style: `background: ${singerTint(singers, colors)}`
        })
      )
      if (!sets.some((set) => set.join(' ') === singers.join(' '))) sets.push(singers)
    })
    if (sets.length > 0) {
      const key = `${sets.map((set) => set.join('+')).join('|')}:${sets.flat().map(nameOf).join('|')}`
      decorations.push(
        Decoration.widget(pos + 1, () => marginTags(sets, colors, nameOf), {
          side: -1,
          key,
          ignoreSelection: true
        })
      )
    }
    return false
  })

  return DecorationSet.create(doc, decorations)
}

const SingerDecorations = Extension.create({
  name: 'singerDecorations',
  addOptions: () => ({ nameOf: (id) => id }),
  addProseMirrorPlugins() {
    const nameOf = (id) => this.options.nameOf(id)
    return [
      new Plugin({
        key: new PluginKey('singerDecorations'),
        props: { decorations: (state) => singerDecorations(state.doc, nameOf) }
      })
    ]
  }
})

/** @param {(id: string) => string} nameOf the name a singer id is shown with */
export function lyricsExtensions(nameOf) {
  return [
    LyricsDocument,
    Text,
    History,
    LyricsLine,
    LyricsComment,
    LyricsDirective,
    LyricsSection,
    Chord,
    Singer,
    SingerDecorations.configure({ nameOf })
  ]
}
