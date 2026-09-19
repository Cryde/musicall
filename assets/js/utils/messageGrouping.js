/**
 * Consecutive messages from one author, collapsed into blocks (#1031).
 *
 * Anchored on the block's first message rather than on the previous one, so a block cannot outgrow
 * its window: with a rolling gap, a long monologue in short bursts becomes one block whose header
 * says 14:02 while its last line is at 14:40.
 *
 * Shared by the band space chat and the thread view, whose resources name the author differently,
 * which is why the author is read through an accessor rather than off a fixed property.
 */

/** How far a block may reach past its first message. */
export const GROUP_WINDOW_MS = 5 * 60 * 1000

/**
 * How long a silence has to last before the next block is worth dating. Below this the blocks read
 * as one conversation and a header on each would be noise; the hover label still gives the exact
 * moment for any single message.
 */
export const SEPARATOR_GAP_MS = 60 * 60 * 1000

/**
 * Calendar days in the reader's own timezone, so local getters rather than UTC ones: a reader east
 * of UTC would otherwise see the boundary fall in the middle of their evening.
 */
function isSameLocalDay(first, next) {
  return (
    first.getFullYear() === next.getFullYear() &&
    first.getMonth() === next.getMonth() &&
    first.getDate() === next.getDate()
  )
}

function continuesBlock(block, authorId, sentAt) {
  // An unknown author never continues anything, and never matches another unknown author: two
  // messages nobody can attribute are not the same person. Without this the whole list collapses
  // into one block the moment an accessor returns nothing.
  if (authorId == null || block.authorId == null) {
    return false
  }

  if (block.authorId !== authorId || Number.isNaN(sentAt.getTime())) {
    return false
  }

  // A day boundary always breaks the block, whatever the clock says: 23:58 and 00:01 are three
  // minutes apart and are not the same conversation.
  if (!isSameLocalDay(block.startedAt, sentAt)) {
    return false
  }

  return sentAt.getTime() - block.startedAt.getTime() <= GROUP_WINDOW_MS
}

/**
 * One pass over the whole list, so prepending an older page regroups across the join and a message
 * arriving live joins the block above it when it qualifies. A per-row helper would do neither, and
 * would be quadratic on a long scrollback.
 *
 * @param {Array<object>} messages ordered oldest first
 * @param {(message: object) => string|undefined} authorIdOf
 * @param {(message: object) => string} sentAtOf
 * @returns {Array<{authorId: string|undefined, startedAt: Date, messages: Array<object>}>}
 */
export function groupMessages(
  messages,
  authorIdOf,
  sentAtOf = (message) => message.creation_datetime
) {
  const blocks = []

  for (const message of messages ?? []) {
    const authorId = authorIdOf(message)
    const sentAt = new Date(sentAtOf(message))
    const current = blocks[blocks.length - 1]

    if (current && continuesBlock(current, authorId, sentAt)) {
      current.messages.push(message)
      current.endedAt = sentAt
      continue
    }

    blocks.push({ authorId, startedAt: sentAt, endedAt: sentAt, messages: [message] })
  }

  return blocks
}

/**
 * Whether a block opens a new stretch of conversation and should carry a dated header.
 *
 * Measured from the end of the block before it, so a long exchange is dated once at its start
 * rather than every time the speaker changes.
 */
export function needsTimeSeparator(previousBlock, block) {
  if (!block) {
    return false
  }

  if (!previousBlock) {
    return true
  }

  if (Number.isNaN(block.startedAt.getTime()) || Number.isNaN(previousBlock.endedAt.getTime())) {
    return false
  }

  if (!isSameLocalDay(previousBlock.endedAt, block.startedAt)) {
    return true
  }

  return block.startedAt.getTime() - previousBlock.endedAt.getTime() >= SEPARATOR_GAP_MS
}
