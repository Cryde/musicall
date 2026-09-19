import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { hasReacted, rolledBackReactions, toggledReactions } from './chatReactionToggle.js'

const thumbsUp = (count, mine) => ({ key: 'thumbs_up', emoji: '👍', count, has_reacted: mine })
const heart = (count, mine) => ({ key: 'heart', emoji: '❤️', count, has_reacted: mine })

describe('hasReacted', () => {
  it('is true only for an emoji the viewer is counted under', () => {
    assert.equal(hasReacted([thumbsUp(2, true)], 'thumbs_up'), true)
    assert.equal(hasReacted([thumbsUp(2, false)], 'thumbs_up'), false)
    assert.equal(hasReacted([thumbsUp(2, true)], 'heart'), false)
  })

  it('tolerates a message whose reactions have not arrived yet', () => {
    assert.equal(hasReacted(undefined, 'thumbs_up'), false)
  })
})

describe('toggledReactions', () => {
  it('adds a pill nobody had used yet', () => {
    assert.deepEqual(toggledReactions([], 'thumbs_up'), [thumbsUp(1, true)])
  })

  it('keeps the palette order when it inserts a pill', () => {
    // Inserted before the heart although it arrives after it, because the palette declares the thumb
    // first and the server orders the same way.
    assert.deepEqual(toggledReactions([heart(1, false)], 'thumbs_up'), [
      thumbsUp(1, true),
      heart(1, false)
    ])
  })

  it('joins an emoji somebody else already used', () => {
    assert.deepEqual(toggledReactions([thumbsUp(2, false)], 'thumbs_up'), [thumbsUp(3, true)])
  })

  it('leaves the pill behind with a smaller count when others still hold it', () => {
    assert.deepEqual(toggledReactions([thumbsUp(3, true)], 'thumbs_up'), [thumbsUp(2, false)])
  })

  it('drops the pill entirely when the viewer was the only one', () => {
    assert.deepEqual(toggledReactions([thumbsUp(1, true), heart(1, false)], 'thumbs_up'), [
      heart(1, false)
    ])
  })

  it('leaves the row alone for an emoji outside the palette', () => {
    // Nothing in the interface can produce one, but the row must not gain a pill the server would
    // refuse with a 422.
    assert.deepEqual(toggledReactions([thumbsUp(1, true)], 'aubergine'), [thumbsUp(1, true)])
  })

  it('does not mutate the row it was given', () => {
    const before = [thumbsUp(1, true)]
    toggledReactions(before, 'heart')
    assert.deepEqual(before, [thumbsUp(1, true)])
  })
})

describe('rolledBackReactions', () => {
  it('undoes an add that failed', () => {
    // The row on screen is the optimistic one, so the viewer's own pill has to come back off it.
    assert.deepEqual(rolledBackReactions([thumbsUp(1, true)], 'thumbs_up', false), [])
  })

  it('undoes a removal that failed', () => {
    assert.deepEqual(rolledBackReactions([], 'thumbs_up', true), [thumbsUp(1, true)])
  })

  it('keeps a reaction another member left while the request was in flight', () => {
    // A refetch landed mid-flight and brought a second person in on the thumb. Rolling back has to
    // take one off that count, not paste back the row as it stood before either of them.
    assert.deepEqual(
      rolledBackReactions([thumbsUp(2, true), heart(1, false)], 'thumbs_up', false),
      [thumbsUp(1, false), heart(1, false)]
    )
  })

  it('keeps a reaction another member left while a removal was in flight', () => {
    // The mirror of the case above: the pill is gone from the viewer's side, somebody else joined it
    // meanwhile, and putting the viewer back on has to add to that count rather than replace it.
    assert.deepEqual(rolledBackReactions([thumbsUp(1, false)], 'thumbs_up', true), [
      thumbsUp(2, true)
    ])
  })

  it('changes nothing when a refetch already put the real row back', () => {
    // has_reacted is where it started, so the server's own answer is already on screen and there is
    // nothing left to undo.
    const live = [thumbsUp(3, false)]
    assert.equal(rolledBackReactions(live, 'thumbs_up', false), live)
  })

  it('changes nothing when a refetch already restored a reaction whose removal failed', () => {
    const live = [thumbsUp(2, true)]
    assert.equal(rolledBackReactions(live, 'thumbs_up', true), live)
  })
})
