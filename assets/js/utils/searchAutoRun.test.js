import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createSearchAutoRun } from './searchAutoRun.js'

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
      for (const [id, { fn, at }] of [...scheduled].sort((a, b) => a[1].at - b[1].at)) {
        if (at <= now && scheduled.has(id)) {
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

/** Lets settled promises run their callbacks. */
const flush = () => new Promise((resolve) => setImmediate(resolve))

/** refresh() answers when the test resolves it, as a request would. */
function build({ failRefresh = false } = {}) {
  const clock = fakeClock()
  const calls = []
  const answers = []
  const autoRun = createSearchAutoRun({
    refresh: () => {
      calls.push('refresh')
      return new Promise((resolve, reject) => answers.push(failRefresh ? reject : resolve))
    },
    record: () => calls.push('record'),
    refreshDelay: 500,
    settleDelay: 3000,
    timers: clock
  })
  const answer = async () => {
    answers.shift()?.()
    await flush()
  }
  return { clock, calls, autoRun, answer }
}

describe('createSearchAutoRun', () => {
  it('refreshes once the changes stop, not on each one', () => {
    const { clock, calls, autoRun } = build()
    autoRun.changed()
    clock.advance(300)
    autoRun.changed()
    clock.advance(300)
    assert.deepEqual(calls, [])
    clock.advance(200)
    assert.deepEqual(calls, ['refresh'])
  })

  it('records the search once its results have stood long enough', async () => {
    const { clock, calls, autoRun, answer } = build()
    autoRun.changed()
    clock.advance(500)
    clock.advance(5000)
    assert.deepEqual(calls, ['refresh'], 'the wait starts when the results arrive')
    await answer()
    clock.advance(2999)
    assert.deepEqual(calls, ['refresh'])
    clock.advance(1)
    assert.deepEqual(calls, ['refresh', 'record'])
    assert.equal(clock.pending, 0)
  })

  it('never records a search a newer change replaced', async () => {
    const { clock, calls, autoRun, answer } = build()
    autoRun.changed()
    clock.advance(500)
    await answer()
    clock.advance(1000)
    autoRun.changed()
    clock.advance(500)
    await answer()
    clock.advance(3000)
    assert.deepEqual(calls, ['refresh', 'refresh', 'record'])
  })

  it('drops an answer that arrives after a newer change', async () => {
    const { clock, calls, autoRun, answer } = build()
    autoRun.changed()
    clock.advance(500)
    autoRun.changed()
    await answer()
    clock.advance(5000)
    assert.deepEqual(calls, ['refresh', 'refresh'])
  })

  it('records at once when the member acts on the results', async () => {
    const { clock, calls, autoRun, answer } = build()
    autoRun.changed()
    clock.advance(500)
    await answer()
    await autoRun.settleNow()
    assert.deepEqual(calls, ['refresh', 'record'])
    clock.advance(5000)
    assert.deepEqual(calls, ['refresh', 'record'])
  })

  it('runs a waiting refresh and records once it answers', async () => {
    const { clock, calls, autoRun, answer } = build()
    autoRun.changed()
    clock.advance(100)
    const settling = autoRun.settleNow()
    assert.deepEqual(calls, ['refresh'])
    await answer()
    await settling
    assert.deepEqual(calls, ['refresh', 'record'])
    assert.equal(clock.pending, 0)
  })

  it('records nothing when there is nothing to record', async () => {
    const { calls, autoRun } = build()
    await autoRun.settleNow()
    assert.deepEqual(calls, [])
  })

  it('never records a refresh that failed', async () => {
    const { clock, calls, autoRun, answer } = build({ failRefresh: true })
    autoRun.changed()
    clock.advance(500)
    await answer()
    clock.advance(5000)
    await autoRun.settleNow()
    assert.deepEqual(calls, ['refresh'])
  })

  it('forgets everything on cancel', async () => {
    const { clock, calls, autoRun, answer } = build()
    autoRun.changed()
    clock.advance(500)
    autoRun.cancel()
    await answer()
    clock.advance(5000)
    await autoRun.settleNow()
    assert.deepEqual(calls, ['refresh'])
    assert.equal(clock.pending, 0)
  })
})
