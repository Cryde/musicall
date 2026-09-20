import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { readReceiptLabel } from './chatReadReceipt.js'

/**
 * Pins the French the chat pane prints under its last message, and the point past which it counts
 * instead of naming.
 *
 * Run with `npm test`.
 */

describe('readReceiptLabel', () => {
  it('says nothing when nobody has read the message', () => {
    assert.equal(readReceiptLabel([]), '')
  })

  it('says nothing when the field is missing, which is what an empty pane hands it', () => {
    assert.equal(readReceiptLabel(undefined), '')
  })

  it('names a single reader', () => {
    assert.equal(readReceiptLabel(['batteur']), 'Vu par batteur')
  })

  it('joins two readers with « et »', () => {
    assert.equal(readReceiptLabel(['batteur', 'guitariste']), 'Vu par batteur et guitariste')
  })

  it('joins three readers with commas and a final « et »', () => {
    assert.equal(
      readReceiptLabel(['batteur', 'chanteuse', 'guitariste']),
      'Vu par batteur, chanteuse et guitariste'
    )
  })

  it('counts the rest past three, never « et 1 autre »', () => {
    assert.equal(
      readReceiptLabel(['batteur', 'chanteuse', 'guitariste', 'pianiste']),
      'Vu par batteur, chanteuse et 2 autres'
    )
    assert.equal(
      readReceiptLabel(['batteur', 'chanteuse', 'guitariste', 'pianiste', 'violoniste']),
      'Vu par batteur, chanteuse et 3 autres'
    )
  })
})
