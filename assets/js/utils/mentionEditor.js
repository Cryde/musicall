/**
 * Reading a chip based composer back into the stored mention format (#1005).
 *
 * The composer shows a name; what gets sent is still `@[<uuid>]`, so nothing on the server changes.
 * This is the seam between the two, and it is deliberately the only thing that knows about both: the
 * walk below is written against `nodeType` / `nodeValue` / `childNodes` rather than against anything
 * browser specific, so it can be tested with plain objects and, the day a real editor takes over the
 * editing surface, only `readMentionParts` is replaced while `toWireFormat` stays put.
 */

/** Rather than `Node.TEXT_NODE`, which does not exist outside a browser and so cannot be tested. */
const TEXT_NODE = 3
const ELEMENT_NODE = 1

/** The attribute a chip carries its member in: `data-mention-id`. */
export const MENTION_ID_ATTRIBUTE = 'mentionId'

/**
 * The composer's content as an ordered list of `{type: 'text', value}` and `{type: 'mention', userId}`.
 *
 * Recurses into unknown elements rather than ignoring them, and that is not defensive programming for
 * its own sake: a browser inserts `<div>`s and `<br>`s into a contenteditable on its own, whatever the
 * key handlers do, and a paste can land a tree. Dropping those would silently eat what somebody wrote.
 *
 * @param {{childNodes: Iterable<any>}} root
 * @returns {Array<{type: 'text', value: string} | {type: 'mention', userId: string}>}
 */
export function readMentionParts(root) {
  const parts = []

  for (const node of root.childNodes ?? []) {
    if (node.nodeType === TEXT_NODE) {
      parts.push({ type: 'text', value: node.nodeValue ?? '' })
      continue
    }

    if (node.nodeType !== ELEMENT_NODE) {
      continue
    }

    const userId = node.dataset?.[MENTION_ID_ATTRIBUTE]
    if (userId) {
      // A chip is atomic: whatever text it renders is a display detail, the id is the content.
      parts.push({ type: 'mention', userId })
      continue
    }

    if (node.tagName === 'BR') {
      parts.push({ type: 'text', value: '\n' })
      continue
    }

    // A block the browser made, typically from Enter or a paste. It starts a line, except at the very
    // beginning, where prepending a newline would add one the writer never typed.
    if (isBlock(node) && parts.length > 0) {
      parts.push({ type: 'text', value: '\n' })
    }
    parts.push(...readMentionParts(node))
  }

  return parts
}

const BLOCK_TAGS = new Set([
  'DIV',
  'P',
  'LI',
  'BLOCKQUOTE',
  'PRE',
  'H1',
  'H2',
  'H3',
  'H4',
  'H5',
  'H6'
])

function isBlock(node) {
  return BLOCK_TAGS.has(node.tagName)
}

/**
 * The parts as the string the API stores, which is what the server's mention resolver reads.
 *
 * @param {Array<{type: string, value?: string, userId?: string}>} parts
 * @returns {string}
 */
export function toWireFormat(parts) {
  return parts
    .map((part) => (part.type === 'mention' ? `@[${part.userId}]` : (part.value ?? '')))
    .join('')
}

/**
 * @param {{childNodes: Iterable<any>}} root
 * @returns {string}
 */
export function serializeEditor(root) {
  return toWireFormat(readMentionParts(root))
}
