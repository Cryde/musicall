import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { createPresenceSession, createTargetedPresence } from './presenceSession.js'

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

/** Timers that remember their delay, so a test can fire the hidden grace and leave the heartbeat be. */
function delayedTimers() {
  const pending = new Map()
  let next = 1
  return {
    set: (fn, delayMs) => {
      const id = next++
      pending.set(id, { fn, delayMs })
      return id
    },
    clear: (id) => pending.delete(id),
    fireAfter(delayMs) {
      for (const [id, timer] of pending) {
        if (timer.delayMs !== delayMs) continue
        pending.delete(id)
        timer.fn()
        return
      }
      throw new Error(`No timer of ${delayMs} ms pending`)
    },
    delays: () => [...pending.values()].map((timer) => timer.delayMs)
  }
}

function setupTargeted() {
  const timers = delayedTimers()
  const beats = []
  const leaves = []
  const presence = createTargetedPresence({
    beat: (target) => beats.push(target),
    leave: (target) => leaves.push(target),
    intervalMs: 30000,
    graceMs: 15000,
    timers
  })
  return { timers, presence, beats, leaves }
}

describe('createTargetedPresence', () => {
  it('beats the open conversation at once, then on every interval', () => {
    const { timers, presence, beats } = setupTargeted()

    presence.target('thread-a', true)
    timers.fireAfter(30000)

    assert.deepEqual(beats, ['thread-a', 'thread-a'])
  })

  it('leaves the conversation it is switching away from before beating the next', () => {
    const { presence, beats, leaves } = setupTargeted()

    presence.target('thread-a', true)
    presence.target('thread-b', true)

    assert.deepEqual(leaves, ['thread-a'])
    assert.deepEqual(beats, ['thread-a', 'thread-b'])
  })

  it('says nothing again for the conversation it is already in', () => {
    const { presence, beats, leaves } = setupTargeted()

    presence.target('thread-a', true)
    presence.target('thread-a', true)

    assert.deepEqual(beats, ['thread-a'])
    assert.deepEqual(leaves, [])
  })

  it('beats nothing without a conversation, even when shown', () => {
    const { timers, presence, beats, leaves } = setupTargeted()

    presence.target('thread-a', true)
    presence.target(null, true)
    presence.shown()

    assert.deepEqual(beats, ['thread-a'])
    assert.deepEqual(leaves, ['thread-a'])
    assert.deepEqual(timers.delays(), [])
  })

  it('waits until shown when the conversation opens in a hidden tab', () => {
    const { presence, beats } = setupTargeted()

    presence.target('thread-a', false)
    assert.deepEqual(beats, [])
    presence.shown()

    assert.deepEqual(beats, ['thread-a'])
  })

  it('leaves the old conversation once when switching during the hidden grace', () => {
    const { timers, presence, beats, leaves } = setupTargeted()

    presence.target('thread-a', true)
    presence.hidden()
    presence.target('thread-b', false)

    assert.deepEqual(leaves, ['thread-a'])
    assert.deepEqual(beats, ['thread-a'])
    assert.deepEqual(timers.delays(), [])
  })

  it('leaves the conversation it is in when the grace runs out', () => {
    const { timers, presence, leaves } = setupTargeted()

    presence.target('thread-a', true)
    presence.hidden()
    timers.fireAfter(15000)

    assert.deepEqual(leaves, ['thread-a'])
    assert.deepEqual(timers.delays(), [])
  })
})
