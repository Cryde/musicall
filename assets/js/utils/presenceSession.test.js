import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createPresenceSession } from './presenceSession.js'

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

function fakeHeartbeat() {
  return {
    starts: 0,
    isRunning: false,
    start() {
      if (this.isRunning) return
      this.isRunning = true
      this.starts++
    },
    stop() {
      this.isRunning = false
    }
  }
}

function setup() {
  const timers = fakeTimers()
  const heartbeat = fakeHeartbeat()
  let leaves = 0
  const session = createPresenceSession({
    heartbeat,
    leave: () => leaves++,
    graceMs: 15000,
    timers
  })
  return { timers, heartbeat, session, leaves: () => leaves }
}

describe('createPresenceSession', () => {
  it('stays here, silently, when shown again within the grace', () => {
    const { timers, heartbeat, session, leaves } = setup()

    session.shown()
    session.hidden()
    session.shown()

    assert.equal(timers.size, 0)
    assert.equal(heartbeat.isRunning, true)
    assert.equal(heartbeat.starts, 1)
    assert.equal(leaves(), 0)
  })

  it('leaves once the grace runs out while hidden', () => {
    const { timers, heartbeat, session, leaves } = setup()

    session.shown()
    session.hidden()
    session.hidden()
    assert.equal(timers.size, 1)
    timers.fire()

    assert.equal(heartbeat.isRunning, false)
    assert.equal(leaves(), 1)
  })

  it('beats again at once when shown after having left', () => {
    const { timers, heartbeat, session } = setup()

    session.shown()
    session.hidden()
    timers.fire()
    session.shown()

    assert.equal(heartbeat.isRunning, true)
    assert.equal(heartbeat.starts, 2)
  })

  it('leaves at once when the page goes, and only once', () => {
    const { timers, session, leaves } = setup()

    session.shown()
    session.hidden()
    session.leaveNow()
    session.leaveNow()

    assert.equal(timers.size, 0)
    assert.equal(leaves(), 1)
  })

  it('says nothing when it never was here', () => {
    const { timers, session, leaves } = setup()

    session.hidden()
    session.leaveNow()

    assert.equal(timers.size, 0)
    assert.equal(leaves(), 0)
  })
})
