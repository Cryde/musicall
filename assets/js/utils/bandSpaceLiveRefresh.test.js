import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  announceBandSpaceChange,
  createBandSpaceLiveRefresher,
  onBandSpaceChange
} from './bandSpaceLiveRefresh.js'

/** A screen refetching on a module change (#1157). Run with `npm test`. */

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
    }
  }
}

function build({ busy = false } = {}) {
  const clock = fakeClock()
  const state = { refreshes: 0, busy }
  const refresher = createBandSpaceLiveRefresher({
    bandSpaceId: () => 'space-1',
    modules: ['task'],
    refresh: () => state.refreshes++,
    isBusy: () => state.busy,
    delayMs: 1000,
    timers: clock
  })

  return { clock, state, refresher }
}

describe('createBandSpaceLiveRefresher', () => {
  it('refetches once for a burst of changes to its module of its space', () => {
    const { clock, state, refresher } = build()

    refresher.handle({ bandSpaceId: 'space-1', module: 'task' })
    refresher.handle({ bandSpaceId: 'space-1', module: 'task' })
    clock.advance(999)
    assert.equal(state.refreshes, 0)
    clock.advance(1)

    assert.equal(state.refreshes, 1)
  })

  it('ignores another space and another module', () => {
    const { clock, state, refresher } = build()

    refresher.handle({ bandSpaceId: 'space-2', module: 'task' })
    refresher.handle({ bandSpaceId: 'space-1', module: 'finance' })
    clock.advance(1000)

    assert.equal(state.refreshes, 0)
  })

  it('refetches on a reconnect', () => {
    const { clock, state, refresher } = build()

    refresher.handle(null)
    clock.advance(1000)

    assert.equal(state.refreshes, 1)
  })

  it('holds the refetch while the screen is busy, then runs it once on resume', () => {
    const { clock, state, refresher } = build({ busy: true })

    refresher.handle({ bandSpaceId: 'space-1', module: 'task' })
    clock.advance(1000)
    assert.equal(state.refreshes, 0)

    state.busy = false
    refresher.resume()
    refresher.resume()
    clock.advance(1000)

    assert.equal(state.refreshes, 1)
  })

  it('does nothing on resume when nothing was held', () => {
    const { clock, state, refresher } = build()

    refresher.resume()
    clock.advance(1000)

    assert.equal(state.refreshes, 0)
  })

  it('drops a pending refetch on cancel', () => {
    const { clock, state, refresher } = build()

    refresher.handle({ bandSpaceId: 'space-1', module: 'task' })
    refresher.cancel()
    clock.advance(1000)

    assert.equal(state.refreshes, 0)
  })
})

describe('announceBandSpaceChange', () => {
  it('reaches every listener until it unsubscribes', () => {
    const received = []
    const unsubscribe = onBandSpaceChange((change) => received.push(change))

    announceBandSpaceChange({ bandSpaceId: 'space-1', module: 'task' })
    unsubscribe()
    announceBandSpaceChange(null)

    assert.deepEqual(received, [{ bandSpaceId: 'space-1', module: 'task' }])
  })
})
