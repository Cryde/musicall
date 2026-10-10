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

function fakeBroadcastBus() {
  const channels = new Set()
  return {
    createChannel(name) {
      let onmessage = null
      const ch = {
        name,
        set onmessage(fn) {
          onmessage = fn
        },
        get onmessage() {
          return onmessage
        },
        postMessage(data) {
          for (const other of channels) {
            if (other !== ch && other.name === name && other.onmessage) {
              other.onmessage({ data })
            }
          }
        },
        close() {
          channels.delete(ch)
        }
      }
      channels.add(ch)
      return ch
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

function setupCoordinated(bus = fakeBroadcastBus(), channelName = 'chat_presence') {
  let leaves = 0
  function createTab(tabId) {
    const timers = fakeTimers()
    const heartbeat = fakeHeartbeat()
    const session = createPresenceSession({
      tabId,
      channelName,
      createChannel: (name) => bus.createChannel(name),
      heartbeat,
      leave: () => leaves++,
      graceMs: 15000,
      timers
    })
    return { timers, heartbeat, session, tabId }
  }
  return { createTab, leaves: () => leaves }
}

describe('createPresenceSession (standalone)', () => {
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

describe('createPresenceSession (multi-tab coordinated)', () => {
  it('elects a single leader when multiple tabs are shown simultaneously', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()

    // Exactly one tab beats as leader (tab-a since tab-a < tab-b)
    assert.equal(tabA.heartbeat.isRunning, true)
    assert.equal(tabB.heartbeat.isRunning, false)
    assert.equal(tabA.heartbeat.starts, 1)
    assert.equal(tabB.heartbeat.starts, 0)
    assert.equal(leaves(), 0)
  })

  it('hands over leadership when the active leader becomes hidden', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()

    // User switches to tab B: tab A becomes hidden, tab B remains shown
    tabA.session.hidden()

    assert.equal(tabA.heartbeat.isRunning, false)
    assert.equal(tabB.heartbeat.isRunning, true)
    assert.equal(tabB.heartbeat.starts, 1)

    // Tab A sees tab B is visible, so it schedules no grace leave
    assert.equal(tabA.timers.size, 0)
    assert.equal(leaves(), 0)
  })

  it('switches back and forth between tabs without firing false leaves', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()

    // Switch to tab B
    tabA.session.hidden()
    assert.equal(tabA.heartbeat.isRunning, false)
    assert.equal(tabB.heartbeat.isRunning, true)

    // Switch back to tab A
    tabB.session.hidden()
    tabA.session.shown()
    assert.equal(tabA.heartbeat.isRunning, true)
    assert.equal(tabB.heartbeat.isRunning, false)

    assert.equal(leaves(), 0)
    assert.equal(tabA.timers.size, 0)
    assert.equal(tabB.timers.size, 0)
  })

  it('does not send leave when closing a tab while another tab is still open and visible', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()

    // Tab A (leader) closes
    tabA.session.leaveNow()

    // Server leave is NOT called because tab B is still active
    assert.equal(leaves(), 0)
    // Tab B takes over leadership
    assert.equal(tabB.heartbeat.isRunning, true)
  })

  it('does not send leave when closing the leader tab while follower is hidden, but follower holds grace', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()
    tabB.session.hidden()

    // Tab A closes
    tabA.session.leaveNow()
    assert.equal(leaves(), 0)

    // Tab B is now the sole tab and is hidden; it holds the grace timer
    assert.equal(tabB.timers.size, 1)

    // Once Tab B's grace timer runs out, presence leaves
    tabB.timers.fire()
    assert.equal(leaves(), 1)
    assert.equal(tabB.heartbeat.isRunning, false)
  })

  it('leaves once if all open tabs remain hidden and the grace runs out', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()

    // Both tabs become hidden (user minimizes browser)
    tabA.session.hidden()
    tabB.session.hidden()

    // Leader (tab A) holds the single grace timer
    assert.equal(tabA.timers.size, 1)
    assert.equal(tabB.timers.size, 0)

    // Grace runs out
    tabA.timers.fire()
    assert.equal(leaves(), 1)
    assert.equal(tabA.heartbeat.isRunning, false)
    assert.equal(tabB.heartbeat.isRunning, false)
  })

  it('cancels grace leave across tabs if a tab becomes visible before grace runs out', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()

    // Both tabs hidden
    tabA.session.hidden()
    tabB.session.hidden()
    assert.equal(tabA.timers.size, 1)

    // Tab B becomes shown before grace runs out
    tabB.session.shown()

    // Grace timer on Tab A is cancelled
    assert.equal(tabA.timers.size, 0)
    assert.equal(tabB.timers.size, 0)
    assert.equal(tabB.heartbeat.isRunning, true)
    assert.equal(leaves(), 0)
  })

  it('leaves immediately when the last remaining tab is closed', () => {
    const { createTab, leaves } = setupCoordinated()
    const tabA = createTab('tab-a')
    const tabB = createTab('tab-b')

    tabA.session.shown()
    tabB.session.shown()

    tabA.session.leaveNow()
    assert.equal(leaves(), 0)

    tabB.session.leaveNow()
    assert.equal(leaves(), 1)
  })
})
