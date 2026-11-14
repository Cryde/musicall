import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { CHECK_INTERVAL_MS, createSessionKeeper } from './sessionKeeper.js'

/** The security store's session logic (#1022). Run with `npm test`. */

const HOUR = 3600
const START = 1_000_000

/** Timers and the epoch clock, which only move when the test says so. */
function fakeClock() {
  let ms = 0
  let nextId = 1
  const scheduled = new Map()

  return {
    seconds: () => START + Math.floor(ms / 1000),
    set(fn, delay) {
      const id = nextId++
      scheduled.set(id, { fn, at: ms + delay })

      return id
    },
    clear(id) {
      scheduled.delete(id)
    },
    pending: () => scheduled.size,
    async advanceMinutes(minutes) {
      for (let i = 0; i < minutes; i++) {
        ms += CHECK_INTERVAL_MS
        for (const [id, { fn, at }] of [...scheduled]) {
          if (at <= ms) {
            scheduled.delete(id)
            await fn()
          }
        }
      }
    }
  }
}

function memoryStorage() {
  const items = new Map()

  return {
    getItem: (key) => items.get(key) ?? null,
    setItem: (key, value) => items.set(key, String(value)),
    removeItem: (key) => items.delete(key)
  }
}

const refused = () => Object.assign(new Error('refused'), { response: { status: 401 } })

/**
 * A browser and a server. `server.answer` is what the next refresh gets: `renew`, `refuse`,
 * `unreachable`, `race` (another tab won, its cookie is there) or `empty` (no cookie comes back).
 */
function build({
  tokenSecondsLeft = null,
  signedInBefore = false,
  storage = memoryStorage()
} = {}) {
  const clock = fakeClock()
  const freshToken = () => ({
    exp: clock.seconds() + HOUR,
    username: 'jeremy',
    roles: ['ROLE_USER']
  })
  const jar = {
    token:
      tokenSecondsLeft === null
        ? null
        : { exp: clock.seconds() + tokenSecondsLeft, username: 'jeremy', roles: ['ROLE_USER'] }
  }
  const server = { answer: 'renew' }
  const state = {
    signedIn: false,
    settled: false,
    user: null,
    expired: 0,
    refreshes: 0,
    failOnSignIn: false
  }

  if (signedInBefore) {
    storage.setItem('was_logged_in', 'true')
  }

  async function refresh() {
    state.refreshes += 1
    server.notify?.()
    switch (server.answer) {
      case 'hold':
        // Answers, with a renewed token, only when the test calls `server.release()`.
        return new Promise((resolve) => {
          server.release = () => {
            jar.token = freshToken()
            resolve()
          }
        })
      case 'renew':
        jar.token = freshToken()
        return
      case 'race':
        jar.token = freshToken()
        throw refused()
      case 'refuse':
        throw refused()
      case 'unreachable':
        throw new Error('Network Error')
      default:
        return
    }
  }

  const keeper = createSessionKeeper({
    session: {
      isSignedIn: () => state.signedIn,
      signedIn(payload) {
        if (state.failOnSignIn) {
          state.failOnSignIn = false
          throw new Error('a store hook that throws')
        }
        state.signedIn = true
        state.settled = true
        state.user = payload.username
      },
      signedOut() {
        state.signedIn = false
        state.settled = true
        state.user = null
      },
      expired() {
        state.expired += 1
      }
    },
    refresh,
    // The browser deletes the cookie the moment the token expires.
    readPayload: () => (jar.token && jar.token.exp > clock.seconds() ? jar.token : null),
    storage,
    now: clock.seconds,
    timers: clock
  })

  const flag = () => storage.getItem('was_logged_in')

  return { keeper, clock, jar, server, state, storage, flag }
}

/** Resolves when the next refresh is sent. */
const nextRefresh = (server) =>
  new Promise((resolve) => {
    server.notify = resolve
  })

describe('createSessionKeeper on arrival', () => {
  it('signs in with a live token and remembers this browser was signed in', async () => {
    // The flag used to be written by the password form only, so a Google or Apple account never had it.
    const { keeper, state, flag } = build({ tokenSecondsLeft: HOUR })

    assert.equal(await keeper.check(), true)

    assert.equal(state.signedIn, true)
    assert.equal(state.user, 'jeremy')
    assert.equal(state.refreshes, 0)
    assert.equal(flag(), 'true')
  })

  it('brings back a Google account more than an hour after its last visit', async () => {
    const storage = memoryStorage()
    await build({ tokenSecondsLeft: HOUR, storage }).keeper.check()

    // A browser restart two hours later: the JWT cookie is long gone, the refresh token is not.
    const later = build({ storage })
    assert.equal(await later.keeper.check(), true)

    assert.equal(later.state.signedIn, true)
    assert.equal(later.state.refreshes, 1)
  })

  it('does not call the server for a browser that never signed in', async () => {
    const { keeper, clock, state } = build()

    assert.equal(await keeper.check(), false)
    await clock.advanceMinutes(5)

    assert.equal(state.signedIn, false)
    assert.equal(state.settled, true)
    assert.equal(state.refreshes, 0)
    assert.equal(clock.pending(), 0)
  })

  it('ends the session when the refresh token is refused', async () => {
    const { keeper, clock, server, state, flag } = build({ signedInBefore: true })
    server.answer = 'refuse'

    assert.equal(await keeper.check(), false)

    assert.equal(state.signedIn, false)
    assert.equal(state.expired, 1)
    assert.equal(flag(), null)
    assert.equal(clock.pending(), 0)
  })

  it('keeps the flag when the refresh never landed, and signs in once the network is back', async () => {
    const { keeper, clock, server, state, flag } = build({ signedInBefore: true })
    server.answer = 'unreachable'

    assert.equal(await keeper.check(), false)

    assert.equal(state.signedIn, false)
    assert.equal(state.settled, true, 'the page stops waiting to know who is signed in')
    assert.equal(state.expired, 0)
    assert.equal(flag(), 'true')

    server.answer = 'renew'
    await clock.advanceMinutes(1)

    assert.equal(state.signedIn, true)
  })

  it('tries again every minute while the server does not answer', async () => {
    const { keeper, clock, server, state } = build({ signedInBefore: true })
    server.answer = 'unreachable'

    await keeper.check()
    await clock.advanceMinutes(3)

    assert.equal(state.refreshes, 4)
  })

  it('gives up when the refresh answers without a usable token, rather than looping', async () => {
    const { keeper, clock, server, state, flag } = build({ signedInBefore: true })
    server.answer = 'empty'

    assert.equal(await keeper.check(), false)

    assert.equal(state.refreshes, 2)
    assert.equal(state.signedIn, false)
    assert.equal(flag(), 'true', 'nothing said the session was over')
    assert.equal(clock.pending(), 0)
  })
})

describe('createSessionKeeper during a session', () => {
  it('leaves a token with plenty of time alone', async () => {
    const { keeper, clock, state } = build({ tokenSecondsLeft: HOUR })

    await keeper.check()
    await clock.advanceMinutes(30)

    assert.equal(state.refreshes, 0)
  })

  it('renews a token about to run out', async () => {
    const { keeper, clock, jar, state } = build({ tokenSecondsLeft: HOUR })
    await keeper.check()
    const first = jar.token

    await clock.advanceMinutes(55)

    assert.equal(state.refreshes, 1)
    assert.notEqual(jar.token, first)
    assert.equal(state.signedIn, true)
  })

  it('stays signed in through renewals that get no answer, past expiry, until one does', async () => {
    const { keeper, clock, server, state, flag } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()
    server.answer = 'unreachable'

    // The buffer starts five minutes before expiry; carry on well past it.
    await clock.advanceMinutes(20)

    assert.equal(state.signedIn, true)
    assert.equal(state.expired, 0)
    assert.equal(flag(), 'true')
    assert.equal(clock.pending(), 1, 'the timer is still running')

    server.answer = 'renew'
    const before = state.refreshes
    await clock.advanceMinutes(1)

    assert.equal(state.refreshes, before + 1)
    assert.equal(state.signedIn, true)
  })

  it('keeps using a token with minutes left when its renewal is refused, then ends at expiry', async () => {
    const { keeper, clock, server, state, flag } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()
    server.answer = 'refuse'

    await clock.advanceMinutes(6)

    assert.equal(state.signedIn, true)
    assert.equal(state.expired, 0)

    await clock.advanceMinutes(5)

    assert.equal(state.signedIn, false)
    assert.equal(state.expired, 1)
    assert.equal(flag(), null)
    assert.equal(clock.pending(), 0)
  })

  it('carries on after losing a two tab race, without clearing the flag the tabs share', async () => {
    const { keeper, clock, server, state, flag } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()
    server.answer = 'race'

    await clock.advanceMinutes(11)

    assert.equal(state.signedIn, true)
    assert.equal(state.expired, 0)
    assert.equal(flag(), 'true')
  })

  it('signs out without asking the server once another tab has logged out', async () => {
    const { keeper, clock, jar, state, storage } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()

    storage.removeItem('was_logged_in')
    jar.token = null
    await clock.advanceMinutes(1)

    assert.equal(state.signedIn, false)
    assert.equal(state.refreshes, 0)
    assert.equal(clock.pending(), 0)
  })

  it('end() forgets this browser was signed in and stops the timer', async () => {
    const { keeper, clock, state, flag } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()

    keeper.end()
    await clock.advanceMinutes(60)

    assert.equal(flag(), null)
    assert.equal(state.refreshes, 0)
    assert.equal(clock.pending(), 0)
  })

  it('expire() signs out, forgets this browser and says why', async () => {
    const { keeper, clock, state, flag } = build({ tokenSecondsLeft: HOUR })
    await keeper.check()

    keeper.expire()

    assert.equal(state.signedIn, false)
    assert.equal(state.expired, 1)
    assert.equal(flag(), null)
    assert.equal(clock.pending(), 0)
  })

  it('never runs two timers, however often it is told to sign in', async () => {
    const { keeper, clock } = build({ tokenSecondsLeft: HOUR })

    await keeper.check()
    await keeper.check()
    await keeper.check()

    assert.equal(clock.pending(), 1)
  })

  it('does not sign back in when the session ends while a renewal is in flight', async () => {
    const { keeper, clock, server, flag } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()
    server.answer = 'hold'

    const sent = nextRefresh(server)
    const advancing = clock.advanceMinutes(6)
    await sent
    keeper.end()
    server.release()
    await advancing

    assert.equal(flag(), null)
    assert.equal(clock.pending(), 0)
  })

  it('keeps one timer when a sign in lands while a tick is renewing', async () => {
    const { keeper, clock, jar, server } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()
    server.answer = 'hold'

    const sent = nextRefresh(server)
    const advancing = clock.advanceMinutes(6)
    await sent
    jar.token = { exp: clock.seconds() + HOUR, username: 'jeremy', roles: ['ROLE_USER'] }
    await keeper.check()
    server.release()
    await advancing

    assert.equal(clock.pending(), 1)
  })

  it('keeps the timer running after a tick that throws', async () => {
    const { keeper, clock, state } = build({ tokenSecondsLeft: 10 * 60 })
    await keeper.check()

    state.failOnSignIn = true
    await assert.rejects(clock.advanceMinutes(6))

    assert.equal(clock.pending(), 1)
    await clock.advanceMinutes(1)
    assert.equal(state.signedIn, true)
  })
})
