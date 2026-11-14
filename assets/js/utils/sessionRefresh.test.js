import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  AUTH_STEP,
  authStep,
  classifyRefreshFailure,
  forgetSignedIn,
  hasLiveToken,
  isAuthEndpoint,
  lockAcrossTabs,
  needsProactiveRefresh,
  REFRESH_BUFFER_SECONDS,
  REFRESH_FAILURE,
  readJwtPayload,
  refreshSession,
  rememberSignedIn,
  secondsUntilExpiry,
  tokenNeedsRenewing,
  wasSignedIn
} from './sessionRefresh.js'

/**
 * A real, unsigned JWT with `{"exp": 2000}`. Built rather than pasted so it is obvious there is no
 * secret in it: only the payload is ever read here, the signature half lives in an httpOnly cookie
 * the browser never lets script see.
 */
const base64Url = (value) => Buffer.from(JSON.stringify(value)).toString('base64url')
const jwtWith = (payload) => `${base64Url({ alg: 'RS256' })}.${base64Url(payload)}.signature`

describe('isAuthEndpoint', () => {
  it('recognises signing in, where a 401 is the answer', () => {
    assert.equal(isAuthEndpoint('/api/login_check'), true)
  })

  it('recognises the refresh call, which must never trigger another refresh', () => {
    assert.equal(isAuthEndpoint('/api/token/refresh'), true)
  })

  it('treats an ordinary call as recoverable', () => {
    assert.equal(isAuthEndpoint('/api/user/notifications/count'), false)
    assert.equal(isAuthEndpoint('/api/band_spaces/1/chat/messages'), false)
  })

  it('treats signing out as recoverable, so an expired token still logs you out properly', () => {
    assert.equal(isAuthEndpoint('/api/token/invalidate'), false)
  })

  it('does not choke on a request with no url', () => {
    assert.equal(isAuthEndpoint(undefined), false)
  })
})

describe('secondsUntilExpiry', () => {
  it('counts the seconds left', () => {
    assert.equal(secondsUntilExpiry({ exp: 1000 }, 700), 300)
  })

  it('never goes negative', () => {
    assert.equal(secondsUntilExpiry({ exp: 1000 }, 5000), 0)
  })

  it('reads a missing token as nothing left', () => {
    assert.equal(secondsUntilExpiry(null, 700), 0)
  })

  it('reads a payload with no expiry as nothing left', () => {
    assert.equal(secondsUntilExpiry({ roles: [] }, 700), 0)
  })
})

describe('needsProactiveRefresh', () => {
  it('renews inside the buffer', () => {
    assert.equal(needsProactiveRefresh(299), true)
  })

  it('renews exactly on the buffer', () => {
    // The bug this replaces: with `<` and a check interval equal to the buffer, the checks landed on
    // 600, then exactly 300, then 0, so the buffer never once fired.
    assert.equal(needsProactiveRefresh(REFRESH_BUFFER_SECONDS), true)
  })

  it('leaves a token with plenty of time alone', () => {
    assert.equal(needsProactiveRefresh(REFRESH_BUFFER_SECONDS + 1), false)
  })

  it('leaves an already expired token to the 401 path', () => {
    // Refreshing from here too would mean two callers racing to consume one single use token.
    assert.equal(needsProactiveRefresh(0), false)
  })
})

describe('readJwtPayload', () => {
  it('reads the payload of a token', () => {
    assert.deepEqual(readJwtPayload(jwtWith({ exp: 2000, username: 'user_base' })), {
      exp: 2000,
      username: 'user_base'
    })
  })

  it('reads a missing cookie as no token, which is the state of any tab open over an hour', () => {
    // Not `undefined`, which is what es-cookie actually returns for an absent cookie: passing it
    // explicitly triggers the default parameter and reads the real `document.cookie`, which does not
    // exist under `node --test`. Every falsy value takes the same branch.
    assert.equal(readJwtPayload(null), null)
    assert.equal(readJwtPayload(''), null)
  })

  it('reads a cookie it cannot decode as no token rather than throwing', () => {
    // Throwing here would take out whichever handler asked, and the answer is the same either way:
    // there is nothing usable, so renew.
    assert.equal(readJwtPayload('not-a-jwt'), null)
  })
})

describe('hasLiveToken', () => {
  it('is true while the token has time left', () => {
    assert.equal(hasLiveToken(1500, { exp: 2000 }), true)
  })

  it('is false once it has run out', () => {
    assert.equal(hasLiveToken(2000, { exp: 2000 }), false)
  })

  it('is false when there is no token at all', () => {
    // This is what decides whether a failed refresh ends the session or is only a lost race.
    assert.equal(hasLiveToken(1500, null), false)
  })
})

/** No other tab, and no cookie: the in-tab sharing alone. */
const inThisTabOnly = { lock: lockAcrossTabs(null), readCookie: () => undefined }

const nowSeconds = () => Math.floor(Date.now() / 1000)

/** A lock another tab is holding until `release()`. */
function heldLock() {
  let release
  const held = new Promise((resolve) => {
    release = resolve
  })

  return { lock: (task) => held.then(task), release }
}

describe('lockAcrossTabs', () => {
  it('queues every tab on one named lock', async () => {
    const names = []
    const locks = {
      request: (name, task) => {
        names.push(name)

        return task()
      }
    }

    const result = await lockAcrossTabs(locks)(() => Promise.resolve('done'))

    assert.equal(result, 'done')
    assert.deepEqual(names, ['musicall-session-refresh'])
  })

  it('runs the task straight away where Web Locks are missing', async () => {
    assert.equal(await lockAcrossTabs(null)(() => Promise.resolve('done')), 'done')
  })
})

describe('refreshSession', () => {
  it('makes one call however many callers ask at once', async () => {
    // The refresh token is single use, so a second call in flight presents one the first has already
    // consumed and is refused. Sharing the promise is what prevents that.
    let calls = 0
    const request = () => {
      calls += 1

      return new Promise((resolve) => setTimeout(() => resolve('done'), 10))
    }

    const results = await Promise.all([
      refreshSession(request, inThisTabOnly),
      refreshSession(request, inThisTabOnly),
      refreshSession(request, inThisTabOnly)
    ])

    assert.equal(calls, 1)
    assert.deepEqual(results, ['done', 'done', 'done'])
  })

  it('starts a new call once the previous one has settled', async () => {
    let calls = 0
    const request = () => {
      calls += 1

      return Promise.resolve('done')
    }

    await refreshSession(request, inThisTabOnly)
    await refreshSession(request, inThisTabOnly)

    assert.equal(calls, 2)
  })

  it('lets the next caller try again after a failure', async () => {
    // A refresh that fails must not leave a rejected promise cached, or every later attempt in the
    // tab inherits that one failure and the session is unrecoverable.
    let calls = 0
    const failing = () => {
      calls += 1

      return Promise.reject(new Error('network'))
    }

    await assert.rejects(() => refreshSession(failing, inThisTabOnly))
    await assert.rejects(() => refreshSession(failing, inThisTabOnly))

    assert.equal(calls, 2)
  })
})

describe('classifyRefreshFailure', () => {
  const refused = { response: { status: 401 } }

  it('ends the session when the refresh token is refused and nothing usable is left', () => {
    assert.equal(classifyRefreshFailure(refused, false), REFRESH_FAILURE.REFUSED)
  })

  it('carries on after losing a two tab race, the winner having written a live token', () => {
    // Nothing is cleared for either tab: the flag is shared, so clearing it here would sign the
    // winning tab out on its next reload.
    assert.equal(classifyRefreshFailure(refused, true), REFRESH_FAILURE.COVERED)
  })

  it('ends nothing over a request that never landed', () => {
    // axios rejects a network error or a timeout with no response at all.
    assert.equal(
      classifyRefreshFailure({ message: 'Network Error' }, false),
      REFRESH_FAILURE.UNANSWERED
    )
  })

  it('ends nothing over a server error, which is no verdict on the token', () => {
    assert.equal(
      classifyRefreshFailure({ response: { status: 502 } }, false),
      REFRESH_FAILURE.UNANSWERED
    )
    assert.equal(
      classifyRefreshFailure({ response: { status: 500 } }, false),
      REFRESH_FAILURE.UNANSWERED
    )
  })

  it('does not choke on a rejection with nothing in it', () => {
    assert.equal(classifyRefreshFailure(undefined, false), REFRESH_FAILURE.UNANSWERED)
  })
})

describe('the signed in flag', () => {
  const memoryStorage = () => {
    const items = new Map()

    return {
      getItem: (key) => items.get(key) ?? null,
      setItem: (key, value) => items.set(key, String(value)),
      removeItem: (key) => items.delete(key)
    }
  }

  const blockedStorage = {
    getItem() {
      throw new Error('SecurityError')
    },
    setItem() {
      throw new Error('SecurityError')
    },
    removeItem() {
      throw new Error('SecurityError')
    }
  }

  it('reads as signed out on a browser that never signed in', () => {
    assert.equal(wasSignedIn(memoryStorage()), false)
  })

  it('remembers a sign in', () => {
    const storage = memoryStorage()

    rememberSignedIn(storage)

    assert.equal(wasSignedIn(storage), true)
  })

  it('keeps the key the store has always used, so existing sessions survive the deploy', () => {
    const storage = memoryStorage()
    storage.setItem('was_logged_in', 'true')

    assert.equal(wasSignedIn(storage), true)
  })

  it('forgets it again', () => {
    const storage = memoryStorage()
    rememberSignedIn(storage)

    forgetSignedIn(storage)

    assert.equal(wasSignedIn(storage), false)
  })

  it('never throws on blocked storage, which would stop the app from booting', () => {
    assert.doesNotThrow(() => rememberSignedIn(blockedStorage))
    assert.doesNotThrow(() => forgetSignedIn(blockedStorage))
    assert.equal(wasSignedIn(blockedStorage), false)
  })

  it('reads missing storage as signed out', () => {
    assert.equal(wasSignedIn(null), false)
    assert.doesNotThrow(() => rememberSignedIn(null))
  })
})

describe('tokenNeedsRenewing', () => {
  it('leaves a token with plenty of time alone', () => {
    assert.equal(tokenNeedsRenewing({ exp: 5000 }, 1000), false)
  })

  it('renews one about to run out', () => {
    assert.equal(tokenNeedsRenewing({ exp: 1000 + REFRESH_BUFFER_SECONDS }, 1000), true)
  })

  it('renews an expired or missing one', () => {
    assert.equal(tokenNeedsRenewing({ exp: 900 }, 1000), true)
    assert.equal(tokenNeedsRenewing(null, 1000), true)
  })
})

describe('authStep', () => {
  it('uses a token with plenty of time left', () => {
    assert.equal(authStep({ exp: 5000 }, 1000, false), AUTH_STEP.USE_TOKEN)
  })

  it('renews a token about to run out, whatever storage says', () => {
    assert.equal(authStep({ exp: 1100 }, 1000, false), AUTH_STEP.RENEW)
  })

  it('renews when the cookie is gone on a browser that was signed in', () => {
    // An hour after the last visit, for every account: a Google or Apple one included, now that the
    // flag is written on any sign in (#1022).
    assert.equal(authStep(null, 1000, true), AUTH_STEP.RENEW)
  })

  it('reads a browser that never signed in as signed out, without calling the server', () => {
    assert.equal(authStep(null, 1000, false), AUTH_STEP.SIGNED_OUT)
  })
})

describe('refreshSession across tabs', () => {
  const countingRequest = () => {
    const request = () => {
      request.calls += 1

      return Promise.resolve()
    }
    request.calls = 0

    return request
  }

  it('sends nothing when another tab renewed while this one waited for the lock', async () => {
    // The race #1022 left open: both tabs presented the same single use token, and a loser whose
    // refusal arrived before the winner's cookie was signed out.
    const { lock, release } = heldLock()
    let cookie
    const request = countingRequest()

    const pending = refreshSession(request, { lock, readCookie: () => cookie })
    cookie = jwtWith({ exp: nowSeconds() + 3600 })
    release()
    await pending

    assert.equal(request.calls, 0)
  })

  it('renews when the cookie is the one it started with, even a live one', async () => {
    // A live `jwt_hp` can be half a pair: `jwt_s` dies with the browser, and the API then refuses it.
    const { lock, release } = heldLock()
    const cookie = jwtWith({ exp: nowSeconds() + 3600 })
    const request = countingRequest()

    const pending = refreshSession(request, { lock, readCookie: () => cookie })
    release()
    await pending

    assert.equal(request.calls, 1)
  })

  it('renews when the cookie changed to one already expired', async () => {
    const { lock, release } = heldLock()
    let cookie
    const request = countingRequest()

    const pending = refreshSession(request, { lock, readCookie: () => cookie })
    cookie = jwtWith({ exp: nowSeconds() - 10 })
    release()
    await pending

    assert.equal(request.calls, 1)
  })

  it('makes no call before the other tab lets go of the lock', async () => {
    const { lock, release } = heldLock()
    const request = countingRequest()

    const pending = refreshSession(request, { lock, readCookie: () => undefined })
    await Promise.resolve()

    assert.equal(request.calls, 0)
    release()
    await pending
    assert.equal(request.calls, 1)
  })
})
