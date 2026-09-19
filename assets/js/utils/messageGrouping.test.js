import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
import {
  GROUP_WINDOW_MS,
  groupMessages,
  needsTimeSeparator,
  SEPARATOR_GAP_MS
} from './messageGrouping.js'

/**
 * The fixtures below are written in `+02:00` and the rule compares calendar days in the reader's own
 * timezone, so the runner's timezone decides whether 23:58 and 00:01 are one day or two. CI runs on
 * UTC, where they are one, and the day boundary tests fail. Pin it, the way agendaDate.test.js does.
 */
const original = process.env.TZ
before(() => {
  process.env.TZ = 'Europe/Paris'
})
after(() => {
  process.env.TZ = original
})

const authorIdOf = (message) => message.author_id

function message(authorId, sentAt, id = sentAt) {
  return { id, author_id: authorId, creation_datetime: sentAt }
}

describe('groupMessages', () => {
  it('collapses a burst from one author into a single block', () => {
    const blocks = groupMessages(
      [
        message('anna', '2026-09-19T14:00:00+02:00'),
        message('anna', '2026-09-19T14:01:00+02:00'),
        message('anna', '2026-09-19T14:04:59+02:00')
      ],
      authorIdOf
    )

    assert.equal(blocks.length, 1)
    assert.equal(blocks[0].messages.length, 3)
    assert.equal(blocks[0].authorId, 'anna')
  })

  it('starts a new block for a different author', () => {
    const blocks = groupMessages(
      [
        message('anna', '2026-09-19T14:00:00+02:00'),
        message('ben', '2026-09-19T14:00:30+02:00'),
        message('anna', '2026-09-19T14:01:00+02:00')
      ],
      authorIdOf
    )

    assert.deepEqual(
      blocks.map((block) => block.authorId),
      ['anna', 'ben', 'anna']
    )
  })

  /**
   * The anchored window is the whole point: the third message is within five minutes of the second
   * but not of the first, and a rolling gap would swallow it.
   */
  it("measures the window from the block's first message, not the previous one", () => {
    const blocks = groupMessages(
      [
        message('anna', '2026-09-19T14:00:00+02:00'),
        message('anna', '2026-09-19T14:04:00+02:00'),
        message('anna', '2026-09-19T14:07:00+02:00')
      ],
      authorIdOf
    )

    assert.equal(blocks.length, 2)
    assert.equal(blocks[0].messages.length, 2)
    assert.equal(blocks[1].messages.length, 1)
  })

  it('keeps a message landing exactly on the boundary', () => {
    const blocks = groupMessages(
      [message('anna', '2026-09-19T14:00:00+02:00'), message('anna', '2026-09-19T14:05:00+02:00')],
      authorIdOf
    )

    assert.equal(blocks.length, 1)
  })

  /**
   * Three minutes apart and not the same conversation. Compared in the reader's own timezone, so
   * this pair is one day apart for a reader in Paris whatever the offset in the payload says.
   */
  it('breaks the block on a change of calendar day', () => {
    const blocks = groupMessages(
      [message('anna', '2026-09-19T23:58:00+02:00'), message('anna', '2026-09-20T00:01:00+02:00')],
      authorIdOf
    )

    assert.equal(blocks.length, 2)
  })

  /**
   * The seam is why this is one pass over the whole array rather than a per-row decision: an older
   * page prepended in front of a block has to be able to join it.
   */
  it('regroups across a prepended older page', () => {
    const older = [message('anna', '2026-09-19T14:00:00+02:00')]
    const loaded = [
      message('anna', '2026-09-19T14:02:00+02:00'),
      message('ben', '2026-09-19T14:03:00+02:00')
    ]

    const blocks = groupMessages([...older, ...loaded], authorIdOf)

    assert.equal(blocks.length, 2)
    assert.equal(blocks[0].messages.length, 2)
  })

  /** The author is read through an accessor because the two resources nest it differently. */
  it('reads a nested author through the accessor', () => {
    const blocks = groupMessages(
      [
        { author: { id: 'anna' }, creation_datetime: '2026-09-19T14:00:00+02:00' },
        { author: { id: 'anna' }, creation_datetime: '2026-09-19T14:01:00+02:00' }
      ],
      (message) => message.author?.id
    )

    assert.equal(blocks.length, 1)
  })

  it('does not group an unreadable datetime into the block above it', () => {
    const blocks = groupMessages(
      [message('anna', '2026-09-19T14:00:00+02:00'), message('anna', 'not-a-date')],
      authorIdOf
    )

    assert.equal(blocks.length, 2)
  })

  /**
   * The failure this rule shipped with: an adapter dropped the author id, every message read as
   * `undefined`, and the whole channel collapsed into one block whose bubbles each kept their own
   * colour. Two messages nobody can attribute are not the same person.
   */
  it('never groups messages whose author cannot be identified', () => {
    const blocks = groupMessages(
      [
        { creation_datetime: '2026-09-19T14:00:00+02:00' },
        { creation_datetime: '2026-09-19T14:01:00+02:00' }
      ],
      (message) => message.author?.id
    )

    assert.equal(blocks.length, 2)
  })

  it('does not attach a known author to a block with an unknown one', () => {
    const blocks = groupMessages(
      [
        { creation_datetime: '2026-09-19T14:00:00+02:00' },
        { author_id: 'anna', creation_datetime: '2026-09-19T14:01:00+02:00' }
      ],
      (message) => message.author_id
    )

    assert.equal(blocks.length, 2)
  })

  it('handles an empty or absent list', () => {
    assert.deepEqual(groupMessages([], authorIdOf), [])
    assert.deepEqual(groupMessages(undefined, authorIdOf), [])
  })
})

describe('needsTimeSeparator', () => {
  const blocksOf = (...stamps) =>
    groupMessages(
      stamps.map((at) => message('anna', at)),
      authorIdOf
    )

  it('dates the first block, which has nothing before it', () => {
    const [first] = blocksOf('2026-09-19T14:00:00+02:00')

    assert.equal(needsTimeSeparator(undefined, first), true)
  })

  /**
   * Below the gap the blocks read as one conversation. Dating each would be noise, and the hover
   * label still gives the exact moment of any single message.
   */
  it('leaves a block undated when it follows closely on the one before', () => {
    const [first, second] = blocksOf('2026-09-19T14:00:00+02:00', '2026-09-19T14:20:00+02:00')

    assert.equal(needsTimeSeparator(first, second), false)
  })

  it('dates a block that opens after an hour of silence', () => {
    const [first, second] = blocksOf('2026-09-19T14:00:00+02:00', '2026-09-19T15:30:00+02:00')

    assert.equal(needsTimeSeparator(first, second), true)
  })

  /** Measured from the end of the previous block, not its start, so a long exchange is dated once. */
  it('measures the silence from the end of the block before it', () => {
    const [first, second] = blocksOf(
      '2026-09-19T14:00:00+02:00',
      '2026-09-19T14:04:00+02:00',
      '2026-09-19T14:30:00+02:00'
    )

    assert.equal(first.messages.length, 2)
    assert.equal(needsTimeSeparator(first, second), false)
  })

  it('dates a block on a new day however short the silence', () => {
    const [first, second] = blocksOf('2026-09-19T23:58:00+02:00', '2026-09-20T00:01:00+02:00')

    assert.equal(needsTimeSeparator(first, second), true)
  })
})
