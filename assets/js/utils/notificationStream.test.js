import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'
import { createNotificationStream, notificationTopic } from './notificationStream.js'

/**
 * The connection lifecycle, which is the part of the live bell with real failure modes: a stream
 * that never opens, two streams open at once, a reconnect that outlives a logout, or credentials
 * renewed on every retry of an outage.
 *
 * Run with `npm test`.
 */

/** Stands in for the browser's EventSource, and lets a test fire its callbacks by hand. */
class FakeEventSource {
  static opened = []

  constructor(url) {
    this.url = url
    this.closed = false
    FakeEventSource.opened.push(this)
  }

  close() {
    this.closed = true
  }
}

/** Runs scheduled callbacks on demand instead of on a clock. */
function fakeTimers() {
  const scheduled = new Map()
  let nextId = 1

  return {
    delays: [],
    set(fn, delay) {
      this.delays.push(delay)
      const id = nextId++
      scheduled.set(id, fn)
      return id
    },
    clear(id) {
      scheduled.delete(id)
    },
    runAll() {
      const pending = [...scheduled.values()]
      scheduled.clear()
      return Promise.all(pending.map((fn) => fn()))
    },
    get pending() {
      return scheduled.size
    }
  }
}

function build(overrides = {}) {
  const timers = fakeTimers()
  const calls = { signals: 0, authRefreshes: 0, payloads: [] }
  const stream = createNotificationStream({
    getTopics: () => ['/users/a-user-id/notifications'],
    onSignal: (payload) => {
      calls.signals += 1
      calls.payloads.push(payload)
    },
    onAuthRefreshNeeded: () => {
      calls.authRefreshes += 1
      return Promise.resolve()
    },
    openStream: (url) => new FakeEventSource(url),
    timers,
    delayFor: () => 1000,
    ...overrides
  })

  return { stream, timers, calls }
}

/** Lets queued promise callbacks run, since the reconnect is chained off a promise. */
const settle = () => new Promise((resolve) => setImmediate(resolve))

beforeEach(() => {
  FakeEventSource.opened = []
})

describe('notificationTopic', () => {
  it('matches the topic MercureTopic::userNotifications() builds', () => {
    assert.equal(notificationTopic('d1be73fc'), '/users/d1be73fc/notifications')
  })
})

describe('createNotificationStream', () => {
  it('subscribes to the signed-in user topic, encoded', () => {
    build().stream.connect()

    assert.equal(FakeEventSource.opened.length, 1)
    assert.equal(
      FakeEventSource.opened[0].url,
      '/.well-known/mercure?topic=%2Fusers%2Fa-user-id%2Fnotifications'
    )
  })

  it('opens nothing when the profile has not arrived yet', () => {
    // The race this whole design exists for: authentication lands before the profile does.
    const { stream } = build({ getTopics: () => [] })
    stream.connect()

    assert.equal(FakeEventSource.opened.length, 0)
  })

  it('opens on a later call once the profile is there', () => {
    let topics = []
    const { stream } = build({ getTopics: () => topics })

    stream.connect()
    assert.equal(FakeEventSource.opened.length, 0)

    topics = ['/users/a-user-id/notifications']
    stream.connect()
    assert.equal(FakeEventSource.opened.length, 1)
  })

  it('multiplexes several topics onto one connection', () => {
    // Mercure carries as many topics as it is given on a single stream. Today the caller passes one,
    // but a second EventSource per topic would be the expensive way to do this and is worth pinning
    // against before the band space chat adds more.
    const { stream } = build({
      getTopics: () => ['/users/a/notifications', '/band-spaces/b/chat']
    })
    stream.connect()

    assert.equal(FakeEventSource.opened.length, 1)
    assert.equal(
      FakeEventSource.opened[0].url,
      '/.well-known/mercure?topic=%2Fusers%2Fa%2Fnotifications&topic=%2Fband-spaces%2Fb%2Fchat'
    )
  })

  it('is idempotent, so two mounting components share one stream', () => {
    const { stream } = build()

    stream.connect()
    stream.connect()
    stream.connect()

    assert.equal(FakeEventSource.opened.length, 1)
  })

  it('refetches on every message the hub sends', () => {
    const { stream, calls } = build()
    stream.connect()

    FakeEventSource.opened[0].onmessage({ data: 'ignored' })

    assert.equal(calls.signals, 1)
  })

  it('closes and reopens on an error rather than leaving it to EventSource', async () => {
    const { stream, timers } = build()
    stream.connect()

    FakeEventSource.opened[0].onerror()
    assert.equal(FakeEventSource.opened[0].closed, true)

    await timers.runAll()
    await settle()

    assert.equal(FakeEventSource.opened.length, 2)
  })

  it('renews credentials every third failure, not on every retry', async () => {
    const { stream, timers, calls } = build()
    stream.connect()

    // Every retry would be a heavier load than the poll this protects, since renewing also refetches
    // the profile. Only at the third does the cookie become the likelier suspect than the network.
    for (let i = 0; i < 2; i++) {
      FakeEventSource.opened.at(-1).onerror()
      await timers.runAll()
      await settle()
    }
    assert.equal(calls.authRefreshes, 0)

    FakeEventSource.opened.at(-1).onerror()
    await timers.runAll()
    await settle()
    assert.equal(calls.authRefreshes, 1)
  })

  it('keeps retrying the renewal, because a renewal can fail too', async () => {
    // Renewing exactly once per outage would strand the stream reconnecting into the same 401
    // forever whenever the first attempt did not produce a usable cookie.
    const { stream, timers, calls } = build({
      onAuthRefreshNeeded: () => {
        calls.authRefreshes += 1

        return Promise.reject(new Error('refresh failed'))
      }
    })
    stream.connect()

    for (let i = 0; i < 9; i++) {
      FakeEventSource.opened.at(-1).onerror()
      await timers.runAll()
      await settle()
    }

    assert.equal(calls.authRefreshes, 3)
  })

  it('a hung renewal does not strand the stream', async () => {
    // The reconnect must not be chained behind the renewal: a refresh request that never settles
    // would otherwise leave no stream, no timer and nothing scheduled.
    const { stream, timers } = build({ onAuthRefreshNeeded: () => new Promise(() => {}) })
    stream.connect()

    for (let i = 0; i < 3; i++) {
      FakeEventSource.opened.at(-1).onerror()
      await timers.runAll()
      await settle()
    }

    assert.equal(FakeEventSource.opened.length, 4)
  })

  it('starts the streak over after a connection that worked', async () => {
    const { stream, timers, calls } = build()
    stream.connect()

    for (let i = 0; i < 3; i++) {
      FakeEventSource.opened.at(-1).onerror()
      await timers.runAll()
      await settle()
    }
    assert.equal(calls.authRefreshes, 1)

    FakeEventSource.opened.at(-1).onopen()
    for (let i = 0; i < 3; i++) {
      FakeEventSource.opened.at(-1).onerror()
      await timers.runAll()
      await settle()
    }

    assert.equal(calls.authRefreshes, 2)
  })

  it('refetches on recovery, because nothing replays what the gap swallowed', async () => {
    const { stream, timers, calls } = build()
    stream.connect()

    // A first open is not a recovery: the store already loaded the count on mount.
    FakeEventSource.opened[0].onopen()
    assert.equal(calls.signals, 0)

    FakeEventSource.opened[0].onerror()
    await timers.runAll()
    await settle()
    FakeEventSource.opened.at(-1).onopen()

    assert.equal(calls.signals, 1)
  })

  it('backs off further the longer an outage lasts', async () => {
    const timers = fakeTimers()
    const { stream } = build({ timers, delayFor: (attempt) => attempt })
    stream.connect()

    for (let i = 0; i < 3; i++) {
      FakeEventSource.opened.at(-1).onerror()
      await timers.runAll()
      await settle()
    }

    assert.deepEqual(timers.delays, [0, 1, 2])
  })

  it('disconnecting closes the stream and cancels a pending retry', async () => {
    const { stream, timers } = build()
    stream.connect()

    FakeEventSource.opened[0].onerror()
    assert.equal(timers.pending, 1)

    stream.disconnect()

    assert.equal(timers.pending, 0)
    await timers.runAll()
    await settle()
    assert.equal(FakeEventSource.opened.length, 1)
  })

  it('a stale error from a closed stream cannot resurrect it', async () => {
    const { stream, timers } = build()
    stream.connect()
    const abandoned = FakeEventSource.opened[0]

    stream.disconnect()
    abandoned.onerror()

    assert.equal(timers.pending, 0)
    await settle()
    assert.equal(FakeEventSource.opened.length, 1)
  })

  it('can be reopened after a clean disconnect', async () => {
    // The generation counter is the one thing that could leave the stream permanently unopenable, so
    // the reopen is worth pinning rather than inferring from the cancellation cases above.
    const { stream } = build()

    stream.connect()
    stream.disconnect()
    stream.connect()

    assert.equal(FakeEventSource.opened.length, 2)
    assert.equal(FakeEventSource.opened[0].closed, true)
    assert.equal(FakeEventSource.opened[1].closed, false)
  })

  it('still reconnects normally after a reopen', async () => {
    const { stream, timers } = build()
    stream.connect()
    stream.disconnect()
    stream.connect()

    FakeEventSource.opened.at(-1).onerror()
    await timers.runAll()
    await settle()

    assert.equal(FakeEventSource.opened.length, 3)
  })
})

describe('the payload handed to onSignal', () => {
  it('is the parsed body, because one topic now carries more than one kind of update', () => {
    const { stream, calls } = build()
    stream.connect()

    FakeEventSource.opened[0].onmessage({ data: '{"type":"message","thread_id":"a-thread-id"}' })

    assert.deepEqual(calls.payloads, [{ type: 'message', thread_id: 'a-thread-id' }])
  })

  it('is null when the body does not parse, so the caller refetches everything', () => {
    // Refetching too much is the safe failure. Throwing out of onmessage would take the handler with
    // it and the stream would look healthy while delivering nothing.
    const { stream, calls } = build()
    stream.connect()

    FakeEventSource.opened[0].onmessage({ data: 'not json' })
    FakeEventSource.opened[0].onmessage({ data: '"a string, not an object"' })

    assert.deepEqual(calls.payloads, [null, null])
  })

  it('is null on a recovered connection, where we cannot know what was missed', () => {
    const { stream, timers, calls } = build()
    stream.connect()
    FakeEventSource.opened[0].onerror()
    timers.runAll()
    FakeEventSource.opened.at(-1).onopen()

    assert.deepEqual(calls.payloads, [null])
  })
})
