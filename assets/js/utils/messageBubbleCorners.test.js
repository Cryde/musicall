import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { bubbleCornerClasses } from './messageBubbleCorners.js'

describe('bubbleCornerClasses', () => {
  it('leaves a lone bubble fully rounded', () => {
    assert.equal(bubbleCornerClasses(0, 1, true), '')
    assert.equal(bubbleCornerClasses(0, 1, false), '')
  })

  it('tucks only the corners facing a neighbour, on the aligned side', () => {
    assert.equal(bubbleCornerClasses(0, 3, true), 'rounded-br-sm')
    assert.equal(bubbleCornerClasses(1, 3, true), 'rounded-tr-sm rounded-br-sm')
    assert.equal(bubbleCornerClasses(2, 3, true), 'rounded-tr-sm')
  })

  it('mirrors for a block on the left', () => {
    assert.equal(bubbleCornerClasses(0, 3, false), 'rounded-bl-sm')
    assert.equal(bubbleCornerClasses(1, 3, false), 'rounded-tl-sm rounded-bl-sm')
    assert.equal(bubbleCornerClasses(2, 3, false), 'rounded-tl-sm')
  })

  it('tucks one corner each for a pair', () => {
    assert.equal(bubbleCornerClasses(0, 2, true), 'rounded-br-sm')
    assert.equal(bubbleCornerClasses(1, 2, true), 'rounded-tr-sm')
  })

  /**
   * Tailwind only generates classes it can find in the source, so a name assembled at runtime would
   * never reach the stylesheet. Every branch has to return literals.
   */
  it('returns only fully written class names', () => {
    const emitted = [0, 1, 2].flatMap((index) => [
      bubbleCornerClasses(index, 3, true),
      bubbleCornerClasses(index, 3, false)
    ])

    for (const classes of emitted) {
      for (const name of classes.split(' ')) {
        assert.match(name, /^rounded-[tb][lr]-sm$/)
      }
    }
  })
})
