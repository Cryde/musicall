import { browserTimers } from './browserTimers.js'

/**
 * When a page counts as « here » (#1040): beating while shown, still here for `graceMs` once hidden,
 * gone after. The grace is what keeps a glance at another tab from sending « leaving » and a fresh
 * arrival to the whole band, and from spending the rate limit on it.
 *
 * Coordinates across browser tabs of the same band space chat via BroadcastChannel (#1171):
 * - Exactly one leader tab runs the periodic heartbeat.
 * - Switching tabs does not send premature « leaving » events.
 * - Grace leave is only triggered when all open tabs have been hidden for longer than `graceMs`.
 * - Closing a tab leaves presence online if other tabs are still open on this chat.
 *
 * @param {object} options
 * @param {{ start: Function, stop: Function, isRunning: boolean }} options.heartbeat
 * @param {() => void} options.leave
 * @param {number} options.graceMs
 * @param {string} [options.channelName]
 * @param {BroadcastChannel} [options.channel]
 * @param {(name: string) => BroadcastChannel} [options.createChannel]
 * @param {string} [options.tabId]
 * @param {{ set: Function, clear: Function }} [options.timers] injected for tests
 */
export function createPresenceSession({
  heartbeat,
  leave,
  graceMs,
  channelName = null,
  channel = null,
  createChannel = (name) =>
    typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel(name) : null,
  tabId = null,
  timers = browserTimers()
}) {
  let pendingLeave = null

  const myTabId =
    tabId ??
    (typeof crypto !== 'undefined' && crypto.randomUUID
      ? crypto.randomUUID()
      : Math.random().toString(36).slice(2))

  let actualChannel = channel ?? (channelName ? createChannel(channelName) : null)
  let myVisibility = 'hidden'

  // Map of peerTabId -> { isVisible: boolean }
  const peers = new Map()

  function isCoordinated() {
    return actualChannel !== null
  }

  function post(msg) {
    if (!actualChannel) return
    try {
      actualChannel.postMessage(msg)
    } catch {
      // Channel might be closed or detached
    }
  }

  function cancelPendingLeave() {
    if (pendingLeave === null) return
    timers.clear(pendingLeave)
    pendingLeave = null
  }

  function computeLeaderId() {
    const all = [
      { id: myTabId, isVisible: myVisibility === 'shown' },
      ...Array.from(peers.entries()).map(([id, p]) => ({ id, isVisible: p.isVisible }))
    ]

    const visible = all.filter((t) => t.isVisible)
    const candidates = visible.length > 0 ? visible : all
    candidates.sort((a, b) => a.id.localeCompare(b.id))
    return candidates[0]?.id ?? myTabId
  }

  function amILeader() {
    return computeLeaderId() === myTabId
  }

  function hasAnyVisibleTab() {
    if (myVisibility === 'shown') return true
    for (const p of peers.values()) {
      if (p.isVisible) return true
    }
    return false
  }

  function hasOtherActiveTabs() {
    return peers.size > 0
  }

  function scheduleGraceLeave() {
    if (pendingLeave !== null) return
    pendingLeave = timers.set(() => {
      pendingLeave = null
      const wasRunning = heartbeat.isRunning
      if (wasRunning) {
        heartbeat.stop()
      }
      post({ type: 'grace_expired', tabId: myTabId })
      if (wasRunning) {
        leave()
      }
    }, graceMs)
  }

  function syncLeadership() {
    const leader = amILeader()
    if (leader) {
      if (!heartbeat.isRunning && (hasAnyVisibleTab() || pendingLeave !== null)) {
        heartbeat.start()
      }
    } else if (heartbeat.isRunning) {
      heartbeat.stop()
    }
  }

  function bindChannel(ch) {
    if (!ch) return
    ch.onmessage = (event) => {
      const data = event?.data
      if (!data || typeof data !== 'object' || data.tabId === myTabId) return

      if (data.type === 'hello') {
        peers.set(data.tabId, { isVisible: !!data.isVisible })
        post({
          type: 'welcome',
          tabId: myTabId,
          isVisible: myVisibility === 'shown'
        })
        if (data.isVisible) {
          cancelPendingLeave()
        }
        syncLeadership()
      } else if (data.type === 'welcome') {
        peers.set(data.tabId, { isVisible: !!data.isVisible })
        if (data.isVisible) {
          cancelPendingLeave()
        }
        syncLeadership()
      } else if (data.type === 'visibility') {
        peers.set(data.tabId, { isVisible: !!data.isVisible })
        if (data.isVisible) {
          cancelPendingLeave()
        } else if (!hasAnyVisibleTab() && amILeader()) {
          scheduleGraceLeave()
        }
        syncLeadership()
      } else if (data.type === 'bye') {
        peers.delete(data.tabId)
        if (!hasAnyVisibleTab() && amILeader()) {
          scheduleGraceLeave()
        }
        syncLeadership()
      } else if (data.type === 'grace_expired') {
        cancelPendingLeave()
        if (heartbeat.isRunning) {
          heartbeat.stop()
        }
      }
    }

    // Announce to any existing tabs
    post({
      type: 'hello',
      tabId: myTabId,
      isVisible: myVisibility === 'shown'
    })
  }

  bindChannel(actualChannel)

  function leaveNow() {
    cancelPendingLeave()

    const hadPeers = hasOtherActiveTabs()
    const wasRunning = heartbeat.isRunning

    post({ type: 'bye', tabId: myTabId })

    if (actualChannel) {
      actualChannel.close()
      actualChannel = null
    }

    if (!wasRunning) {
      return
    }

    heartbeat.stop()

    if (hadPeers) {
      return
    }

    leave()
  }

  return {
    shown() {
      myVisibility = 'shown'
      cancelPendingLeave()

      if (!actualChannel && channelName) {
        actualChannel = createChannel(channelName)
        bindChannel(actualChannel)
      }

      post({ type: 'visibility', tabId: myTabId, isVisible: true })

      if (!isCoordinated()) {
        heartbeat.start()
        return
      }

      syncLeadership()
    },

    hidden() {
      myVisibility = 'hidden'

      post({ type: 'visibility', tabId: myTabId, isVisible: false })

      if (!isCoordinated()) {
        if (pendingLeave !== null || !heartbeat.isRunning) return
        scheduleGraceLeave()
        return
      }

      // If another tab is visible, don't schedule leave
      if (hasAnyVisibleTab()) {
        syncLeadership()
        return
      }

      // All tabs are hidden
      if (amILeader() && heartbeat.isRunning) {
        scheduleGraceLeave()
      }
      syncLeadership()
    },

    leaveNow
  }
}
