import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createCoalescedCall } from './coalescedCall.js'

/**
 * The client half of the read receipt cascade guard (#977): a burst of read signals has to cost one
 * refetch, not one per member who read. Run with `npm test`.
 */

/** A clock that only moves when the test says so. */
function fakeClock() {
  let now = 0
  let nextId = 1
  const scheduled = new Map()

  return {
    set(fn, delay) {
      const id = nextId++
      scheduled.set(id, { fn, at: now + delay })
      return id
    },
    clear(id) {
      scheduled.delete(id)
    },
    advance(ms) {
      now += ms
      for (const [id, { fn, at }] of [...scheduled]) {
        if (at <= now) {
          scheduled.delete(id)
          fn()
        }
      }
    },
    get pending() {
      return scheduled.size
    }
  }
}

function build() {
  const clock = fakeClock()
  let calls = 0
  const coalesced = createCoalescedCall({
    delayMs: 1500,
    call: () => {
      calls += 1
    },
    timers: clock
  })

  return { clock, coalesced, calls: () => calls }
}

describe('createCoalescedCall', () => {
  it('turns a burst into one call at the end of the window', () => {
    const { clock, coalesced, calls } = build()

    coalesced.request()
    clock.advance(400)
    coalesced.request()
    clock.advance(400)
    coalesced.request()
    coalesced.request()

    assert.equal(calls(), 0, 'nothing fires before the window closes')
    clock.advance(700)
    assert.equal(calls(), 1)
    clock.advance(10_000)
    assert.equal(calls(), 1, 'the burst is spent, nothing fires again on its own')
  })

  it('does not let a steady trickle postpone the call, unlike a debounce', () => {
    const { clock, coalesced, calls } = build()

    coalesced.request()
    for (let i = 0; i < 5; i += 1) {
      clock.advance(300)
      coalesced.request()
    }

    assert.equal(calls(), 1, 'answered 1500 ms after the first request')
  })

  it('makes two calls for two bursts further apart than the window', () => {
    const { clock, coalesced, calls } = build()

    coalesced.request()
    coalesced.request()
    clock.advance(1500)
    clock.advance(3000)
    coalesced.request()
    coalesced.request()
    clock.advance(1500)

    assert.equal(calls(), 2)
  })

  it('fires nothing after a cancel, and leaves no timer behind', () => {
    const { clock, coalesced, calls } = build()

    coalesced.request()
    coalesced.cancel()
    clock.advance(10_000)

    assert.equal(calls(), 0)
    assert.equal(clock.pending, 0)
  })

  it('opens a fresh window after a cancel', () => {
    const { clock, coalesced, calls } = build()

    coalesced.request()
    coalesced.cancel()
    coalesced.request()
    clock.advance(1500)

    assert.equal(calls(), 1)
  })

  it('treats a cancel with nothing pending as a no-op', () => {
    const { clock, coalesced, calls } = build()

    coalesced.cancel()
    coalesced.request()
    clock.advance(1500)

    assert.equal(calls(), 1)
  })
})
