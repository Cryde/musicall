import assert from 'node:assert/strict'
import { afterEach, beforeEach, describe, it } from 'node:test'
import { browserTimers } from './browserTimers.js'
import { createCoalescedCall } from './coalescedCall.js'
import { createNotificationStream } from './notificationStream.js'

/**
 * Node lets `setTimeout` run on any `this`, a browser does not, so every other test here would pass
 * against the bug this file exists for. These swap the global timers for ones that refuse, the way a
 * browser does, and then drive each module through its *default* timers.
 *
 * Run with `npm test`.
 */

const HOST_ERROR = "'setTimeout' called on an object that does not implement interface Window."

let original
let scheduled

beforeEach(() => {
  original = { setTimeout: globalThis.setTimeout, clearTimeout: globalThis.clearTimeout }
  scheduled = []
  // A plain call has `this` undefined, which a browser accepts; a method call does not.
  globalThis.setTimeout = function (callback, delayMs) {
    if (this !== undefined && this !== globalThis) {
      throw new TypeError(HOST_ERROR)
    }
    scheduled.push({ callback, delayMs })
    return scheduled.length
  }
  globalThis.clearTimeout = function () {
    if (this !== undefined && this !== globalThis) {
      throw new TypeError(HOST_ERROR.replace('setTimeout', 'clearTimeout'))
    }
  }
})

afterEach(() => {
  Object.assign(globalThis, original)
})

describe('browserTimers', () => {
  it('can be called as methods of the object it returns, which is how every caller uses it', () => {
    const timers = browserTimers()

    const handle = timers.set(() => {}, 50)
    timers.clear(handle)

    assert.deepEqual(
      scheduled.map(({ delayMs }) => delayMs),
      [50]
    )
  })
})

describe('the modules that default to real timers', () => {
  it('lets a coalesced call schedule and cancel through its default timers', () => {
    const coalesced = createCoalescedCall({ delayMs: 1500, call: () => {} })

    coalesced.request()
    coalesced.cancel()

    assert.equal(scheduled.length, 1)
  })

  it('lets the notification stream schedule its reconnect after a failed connection', () => {
    const opened = []
    const stream = createNotificationStream({
      getTopics: () => ['/users/1/notifications'],
      onSignal: () => {},
      onAuthRefreshNeeded: async () => {},
      openStream: (url) => {
        const source = { url, close() {} }
        opened.push(source)
        return source
      }
    })

    stream.connect()
    // The path an expired subscriber cookie takes: EventSource gives up, so the stream has to reconnect.
    opened[0].onerror()

    assert.equal(scheduled.length, 1, 'the reconnect was scheduled')
    stream.disconnect()
  })
})
