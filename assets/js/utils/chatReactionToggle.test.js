import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  createReactionToggle,
  hasReacted,
  rolledBackReactions,
  toggledReactions
} from './chatReactionToggle.js'

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

describe('createReactionToggle', () => {
  function setup({ reactions = [], add, remove } = {}) {
    const held = new Map([['m1', { id: 'm1', reactions }]])
    const errors = []
    const toggle = createReactionToggle({
      findMessage: (id) => held.get(id),
      patchReactions: (id, next) => held.set(id, { ...held.get(id), reactions: next }),
      add: add ?? (async () => ({ reactions: [thumbsUp(3, true)] })),
      remove: remove ?? (async () => {}),
      onError: (e) => errors.push(e)
    })

    return { held, errors, toggle }
  }

  it('shows the tap at once and then takes the server row', async () => {
    let answer
    const { held, toggle } = setup({
      add: () =>
        new Promise((resolve) => {
          answer = resolve
        })
    })

    const done = toggle.toggle('m1', 'thumbs_up')
    assert.deepEqual(held.get('m1').reactions, [thumbsUp(1, true)])
    assert.deepEqual(toggle.pendingMessageIds(), new Set(['m1']))

    answer({ reactions: [thumbsUp(3, true)] })
    await done
    assert.deepEqual(held.get('m1').reactions, [thumbsUp(3, true)])
    assert.deepEqual(toggle.pendingMessageIds(), new Set())
  })

  it('removes a reaction the viewer holds', async () => {
    const removed = []
    const { held, toggle } = setup({
      reactions: [thumbsUp(2, true)],
      remove: async (id, key, context) => removed.push([id, key, context])
    })

    await toggle.toggle('m1', 'thumbs_up', 'thread-1')

    assert.deepEqual(held.get('m1').reactions, [thumbsUp(1, false)])
    assert.deepEqual(removed, [['m1', 'thumbs_up', 'thread-1']])
  })

  it('ignores a second tap on the same pill while the first is in flight', async () => {
    let calls = 0
    const { toggle } = setup({
      add: async () => {
        calls += 1
        return { reactions: [thumbsUp(1, true)] }
      }
    })

    await Promise.all([toggle.toggle('m1', 'thumbs_up'), toggle.toggle('m1', 'thumbs_up')])

    assert.equal(calls, 1)
  })

  it('rolls back and reports a refused tap', async () => {
    const refusal = new Error('refusé')
    const { held, errors, toggle } = setup({
      add: async () => {
        throw refusal
      }
    })

    await toggle.toggle('m1', 'thumbs_up')

    assert.deepEqual(held.get('m1').reactions, [])
    assert.deepEqual(errors, [refusal])
  })

  it('reports nothing once the message is no longer held', async () => {
    let fail
    const { held, errors, toggle } = setup({
      add: () =>
        new Promise((_resolve, reject) => {
          fail = reject
        })
    })

    const done = toggle.toggle('m1', 'thumbs_up')
    held.delete('m1')
    fail(new Error('refusé'))
    await done

    assert.deepEqual(errors, [])
  })

  it('does nothing for a message that is not held', async () => {
    let calls = 0
    const { toggle } = setup({
      add: async () => {
        calls += 1
      }
    })

    await toggle.toggle('elsewhere', 'thumbs_up')

    assert.equal(calls, 0)
  })
})
