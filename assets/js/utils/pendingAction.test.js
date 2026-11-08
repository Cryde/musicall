import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  clearPendingAction,
  isOnPendingActionPage,
  keepPendingAction,
  PENDING_ACTION_LIFETIME_MS,
  readPendingAction,
  resumePendingAction
} from './pendingAction.js'

/**
 * What a visitor started before signing in has to come back on its page, once, for a short while, and only to the
 * account that signs in right after. Run with `npm test`.
 */

const NOW = Date.UTC(2026, 10, 8, 12, 0, 0)

function fakeStorage(initial = {}) {
  const values = { ...initial }

  return {
    values,
    getItem: (key) => (key in values ? values[key] : null),
    setItem: (key, value) => {
      values[key] = value
    },
    removeItem: (key) => {
      delete values[key]
    }
  }
}

const throwingStorage = {
  getItem: () => {
    throw new Error('blocked')
  },
  setItem: () => {
    throw new Error('blocked')
  },
  removeItem: () => {
    throw new Error('blocked')
  }
}

function announce(overrides = {}) {
  return {
    kind: 'announce.create',
    route: '/search?instrument=guitar',
    payload: { type: 1, instrument: 'guitar', styles: ['rock', 'blues'], note: 'Salut' },
    stoppedOnSubmit: true,
    ...overrides
  }
}

function keptAnnounce(storage, overrides = {}) {
  keepPendingAction(storage, announce(overrides), { isVisitor: true, now: NOW })
}

describe('keepPendingAction and readPendingAction', () => {
  it('hands back what the visitor started, stamped with its time', () => {
    const storage = fakeStorage()

    assert.equal(keepPendingAction(storage, announce(), { isVisitor: true, now: NOW }), true)
    assert.deepEqual(readPendingAction(storage, NOW + 1000), { ...announce(), createdAt: NOW })
  })

  it('keeps one action at a time, the newest', () => {
    const storage = fakeStorage()
    keptAnnounce(storage)
    keepPendingAction(
      storage,
      { kind: 'message.send', route: '/search', payload: { text: 'Hello' }, stoppedOnSubmit: true },
      { isVisitor: true, now: NOW + 5 }
    )

    assert.equal(readPendingAction(storage, NOW + 10).kind, 'message.send')
    assert.equal(Object.keys(storage.values).length, 1)
  })

  it('reads the action without taking it, so it waits until resumed or dismissed', () => {
    const storage = fakeStorage()
    keptAnnounce(storage)
    readPendingAction(storage, NOW)

    assert.equal(readPendingAction(storage, NOW).kind, 'announce.create')
  })

  it('refuses a signed in member, so an action is only ever written by a visitor', () => {
    const storage = fakeStorage()

    assert.equal(keepPendingAction(storage, announce(), { isVisitor: false, now: NOW }), false)
    assert.deepEqual(storage.values, {})
  })

  it('keeps the action for two hours, then drops it', () => {
    const storage = fakeStorage()
    keptAnnounce(storage)

    assert.notEqual(readPendingAction(storage, NOW + PENDING_ACTION_LIFETIME_MS), null)
    assert.equal(readPendingAction(storage, NOW + PENDING_ACTION_LIFETIME_MS + 1), null)
    assert.deepEqual(storage.values, {})
  })

  it('drops an action dated in the future, which a changed clock would otherwise keep forever', () => {
    const storage = fakeStorage()
    keptAnnounce(storage)

    assert.equal(readPendingAction(storage, NOW - 1), null)
    assert.deepEqual(storage.values, {})
  })

  for (const route of [
    'https://evil.example/search',
    '//evil.example',
    '/\\evil.example',
    'search',
    ''
  ]) {
    it(`refuses to keep an action for the route ${JSON.stringify(route)}`, () => {
      const storage = fakeStorage()

      assert.equal(
        keepPendingAction(storage, announce({ route }), { isVisitor: true, now: NOW }),
        false
      )
      assert.deepEqual(storage.values, {})
    })
  }

  it('drops an action whose stored route was tampered with', () => {
    const storage = fakeStorage()
    keptAnnounce(storage)
    const stored = JSON.parse(storage.values['pending-action'])
    storage.values['pending-action'] = JSON.stringify({ ...stored, route: '//evil.example' })

    assert.equal(readPendingAction(storage, NOW), null)
    assert.deepEqual(storage.values, {})
  })

  for (const [name, payload] of [
    ['a date', { at: new Date(NOW) }],
    ['a function', { send: () => {} }],
    ['a class instance', { file: new Map() }],
    ['an undefined value', { note: undefined }],
    ['a non finite number', { count: Number.NaN }]
  ]) {
    it(`refuses a payload holding ${name}`, () => {
      const storage = fakeStorage()

      assert.equal(
        keepPendingAction(storage, announce({ payload }), { isVisitor: true, now: NOW }),
        false
      )
    })
  }

  it('refuses an action with no kind or no stoppedOnSubmit flag', () => {
    const storage = fakeStorage()

    assert.equal(
      keepPendingAction(storage, announce({ kind: '' }), { isVisitor: true, now: NOW }),
      false
    )
    assert.equal(
      keepPendingAction(storage, announce({ stoppedOnSubmit: undefined }), {
        isVisitor: true,
        now: NOW
      }),
      false
    )
  })

  it('stores only the fields of the action, nothing the caller added', () => {
    const storage = fakeStorage()
    keepPendingAction(storage, { ...announce(), password: 'secret' }, { isVisitor: true, now: NOW })

    assert.equal('password' in JSON.parse(storage.values['pending-action']), false)
  })

  it('drops what cannot be parsed', () => {
    const storage = fakeStorage({ 'pending-action': '{not json' })

    assert.equal(readPendingAction(storage, NOW), null)
    assert.deepEqual(storage.values, {})
  })

  it('costs nothing but the action when the browser blocks storage', () => {
    assert.equal(
      keepPendingAction(throwingStorage, announce(), { isVisitor: true, now: NOW }),
      false
    )
    assert.equal(readPendingAction(throwingStorage, NOW), null)
    assert.doesNotThrow(() => clearPendingAction(throwingStorage))
    assert.equal(keepPendingAction(null, announce(), { isVisitor: true, now: NOW }), false)
    assert.equal(readPendingAction(null, NOW), null)
  })
})

describe('clearPendingAction', () => {
  it('forgets the action, as logout does', () => {
    const storage = fakeStorage({ other: 'kept' })
    keptAnnounce(storage)
    clearPendingAction(storage)

    assert.equal(readPendingAction(storage, NOW), null)
    assert.deepEqual(storage.values, { other: 'kept' })
  })
})

describe('isOnPendingActionPage', () => {
  it('matches the path of the route, whatever the query string or fragment', () => {
    assert.equal(isOnPendingActionPage(announce(), '/search'), true)
    assert.equal(isOnPendingActionPage(announce({ route: '/search#results' }), '/search'), true)
  })

  it('does not match another page', () => {
    assert.equal(isOnPendingActionPage(announce(), '/'), false)
    assert.equal(isOnPendingActionPage(announce(), '/search/guitarist'), false)
  })
})

describe('resumePendingAction', () => {
  function recorder() {
    const calls = []

    return {
      calls,
      handlers: {
        drop: () => calls.push('drop'),
        offer: (label, resume) => calls.push(['offer', label, resume]),
        inform: (sentence) => calls.push(['inform', sentence]),
        isStillOnPage: () => true
      }
    }
  }

  function resumerFor(calls) {
    return async (payload) => ({
      label: 'Créer ton annonce',
      resume: () => calls.push(['resume', payload])
    })
  }

  it('reopens on its own an action the visitor was stopped on a submit', async () => {
    const { calls, handlers } = recorder()
    const action = { ...announce(), createdAt: NOW }

    assert.equal(await resumePendingAction(action, resumerFor(calls), handlers), 'reopened')
    assert.deepEqual(calls, ['drop', ['resume', action.payload]])
  })

  it('offers an action started elsewhere, and resumes it only once accepted', async () => {
    const { calls, handlers } = recorder()
    const action = { ...announce({ stoppedOnSubmit: false }), createdAt: NOW }

    assert.equal(await resumePendingAction(action, resumerFor(calls), handlers), 'offered')
    assert.equal(calls.length, 1)
    const [kind, label, resume] = calls[0]
    assert.equal(kind, 'offer')
    assert.equal(label, 'Créer ton annonce')

    resume()
    assert.deepEqual(calls.slice(1), ['drop', ['resume', action.payload]])
  })

  it('resumes an accepted action once, however often the toast is clicked', async () => {
    const { calls, handlers } = recorder()
    const action = { ...announce({ stoppedOnSubmit: false }), createdAt: NOW }
    await resumePendingAction(action, resumerFor(calls), handlers)
    const [, , resume] = calls[0]

    resume()
    resume()
    assert.deepEqual(calls.slice(1), ['drop', ['resume', action.payload]])
  })

  it('says why and drops an action that is no longer possible, without resuming it', async () => {
    const { calls, handlers } = recorder()
    const action = { ...announce(), createdAt: NOW }
    const resumer = async () => ({ unavailable: 'Ce membre a supprimé son compte.' })

    assert.equal(await resumePendingAction(action, resumer, handlers), 'unavailable')
    assert.deepEqual(calls, ['drop', ['inform', 'Ce membre a supprimé son compte.']])
  })

  it('keeps the action when the member left the page while the resumer was checking', async () => {
    const { calls, handlers } = recorder()
    const action = { ...announce(), createdAt: NOW }

    const result = await resumePendingAction(action, resumerFor(calls), {
      ...handlers,
      isStillOnPage: () => false
    })

    assert.equal(result, 'left')
    assert.deepEqual(calls, [])
  })

  for (const [name, outcome] of [
    ['nothing', undefined],
    ['null', null],
    ['a string', 'Créer ton annonce']
  ]) {
    it(`keeps the action, untouched, when the resumer answers ${name}`, async (t) => {
      t.mock.method(console, 'warn', () => {})
      const { calls, handlers } = recorder()
      const action = { ...announce(), createdAt: NOW }

      assert.equal(await resumePendingAction(action, async () => outcome, handlers), 'invalid')
      assert.deepEqual(calls, [])
      assert.equal(console.warn.mock.callCount(), 1)
    })
  }
})
