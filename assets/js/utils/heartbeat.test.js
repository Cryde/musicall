import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createHeartbeat } from './heartbeat.js'

function fakeTimers() {
  const pending = new Map()
  let next = 1
  return {
    set: (fn) => {
      const id = next++
      pending.set(id, fn)
      return id
    },
    clear: (id) => pending.delete(id),
    fire() {
      const [[id, fn]] = pending
      pending.delete(id)
      fn()
    },
    get size() {
      return pending.size
    }
  }
}

describe('createHeartbeat', () => {
  it('beats at once, then on every tick', () => {
    const timers = fakeTimers()
    let beats = 0
    const heartbeat = createHeartbeat({ intervalMs: 30000, beat: () => beats++, timers })

    heartbeat.start()
    assert.equal(beats, 1)
    timers.fire()
    timers.fire()
    assert.equal(beats, 3)
  })

  it('does not stack when started twice, and stops for good', () => {
    const timers = fakeTimers()
    let beats = 0
    const heartbeat = createHeartbeat({ intervalMs: 30000, beat: () => beats++, timers })

    heartbeat.start()
    heartbeat.start()
    assert.equal(beats, 1)
    assert.equal(timers.size, 1)

    heartbeat.stop()
    assert.equal(heartbeat.isRunning, false)
    assert.equal(timers.size, 0)
  })
})
