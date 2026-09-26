/**
 * Between the ChordPro blocks of chordpro.js and the TipTap document of the simple editor (#1055).
 * A chord is an inline atom, a singer range is a mark, a section wraps its lines. Pure, so it can be
 * tested without a browser.
 */

export const NODE = {
  section: 'lyricsSection',
  line: 'lyricsLine',
  comment: 'lyricsComment',
  directive: 'lyricsDirective',
  chord: 'chord'
}
export const SINGER_MARK = 'singer'

function lineToNode(line) {
  const bounds = new Set([0, line.text.length, ...line.chords.map((chord) => chord.pos)])
  for (const range of line.ranges) {
    bounds.add(range.start)
    bounds.add(range.end)
  }
  const sorted = [...bounds].sort((a, b) => a - b)
  const content = []

  for (let index = 0; index < sorted.length; index++) {
    const from = sorted[index]
    for (const chord of line.chords.filter((candidate) => candidate.pos === from)) {
      content.push({ type: NODE.chord, attrs: { name: chord.name } })
    }
    const to = sorted[index + 1]
    if (to === undefined || to === from) continue
    const range = line.ranges.find((candidate) => from >= candidate.start && from < candidate.end)
    const text = { type: 'text', text: line.text.slice(from, to) }
    if (range) text.marks = [{ type: SINGER_MARK, attrs: { singers: range.singers } }]
    content.push(text)
  }

  return content.length > 0 ? { type: NODE.line, content } : { type: NODE.line }
}

function leafToNode(block) {
  if (block.type === 'line') return lineToNode(block)
  if (block.type === 'comment')
    return block.text
      ? { type: NODE.comment, content: [{ type: 'text', text: block.text }] }
      : { type: NODE.comment }
  return { type: NODE.directive, attrs: { raw: block.raw } }
}

export function toDoc(blocks) {
  const content = blocks.map((block) =>
    block.type === 'section'
      ? {
          type: NODE.section,
          attrs: { kind: block.kind, label: block.label },
          // A section holds at least one line, so an empty one stays somewhere to type.
          content: block.content.length > 0 ? block.content.map(leafToNode) : [{ type: NODE.line }]
        }
      : leafToNode(block)
  )
  return { type: 'doc', content: content.length > 0 ? content : [{ type: NODE.line }] }
}

function nodeToLine(node) {
  let text = ''
  const chords = []
  const ranges = []

  for (const child of node.content ?? []) {
    if (child.type === NODE.chord) {
      chords.push({ pos: text.length, name: child.attrs.name })
      continue
    }
    if (child.type !== 'text') continue
    const singers = child.marks?.find((mark) => mark.type === SINGER_MARK)?.attrs?.singers ?? []
    const start = text.length
    text += child.text
    if (singers.length === 0) continue
    const last = ranges[ranges.length - 1]
    // A chord between two runs of the same singers does not split the range it sits in.
    if (last && last.end === start && last.singers.join(' ') === singers.join(' ')) {
      last.end = text.length
    } else {
      ranges.push({ start, end: text.length, singers: [...singers] })
    }
  }

  return { type: 'line', text, chords, ranges }
}

function nodeToLeaf(node) {
  if (node.type === NODE.comment)
    return { type: 'comment', text: (node.content ?? []).map((child) => child.text ?? '').join('') }
  if (node.type === NODE.directive) return { type: 'directive', raw: node.attrs.raw }
  return nodeToLine(node)
}

export function fromDoc(doc) {
  return (doc.content ?? []).map((node) =>
    node.type === NODE.section
      ? {
          type: 'section',
          kind: node.attrs.kind,
          label: node.attrs.label ?? '',
          content: (node.content ?? []).map(nodeToLeaf)
        }
      : nodeToLeaf(node)
  )
}
