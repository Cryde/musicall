import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  hasLiveToken,
  isAuthEndpoint,
  needsProactiveRefresh,
  REFRESH_BUFFER_SECONDS,
  readJwtPayload,
  refreshSession,
  secondsUntilExpiry
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
      refreshSession(request),
      refreshSession(request),
      refreshSession(request)
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

    await refreshSession(request)
    await refreshSession(request)

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

    await assert.rejects(() => refreshSession(failing))
    await assert.rejects(() => refreshSession(failing))

    assert.equal(calls, 2)
  })
})
