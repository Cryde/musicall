import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  livePositionStorageKey,
  openLivePositionStorage,
  readLivePosition,
  writeLivePosition
} from './setlistLivePosition.js'

/**
 * These pin the rule that keeps a singer on the right track after a reload. Live mode is used on a
 * phone on stage, where a backgrounded tab gets evicted without warning, so a position that comes
 * back wrong is worse than no position at all: every read has to be checked against the setlist as
 * it stands now, and fall back to the first item rather than throw or land past the end.
 *
 * Run with `npm test`.
 */

const SETLIST_ID = '7f3c9b1e-0000-4000-8000-000000000001'
const OTHER_SETLIST_ID = '7f3c9b1e-0000-4000-8000-000000000002'

/** Minimal Storage stand in: the two methods the helpers use, over a plain object. */
function fakeStorage(initial = {}) {
  const values = { ...initial }

  return {
    values,
    getItem: (key) => (key in values ? values[key] : null),
    setItem: (key, value) => {
      values[key] = value
    }
  }
}

/** A Storage that refuses every operation, the way a browser with storage blocked does. */
function throwingStorage() {
  return {
    getItem: () => {
      throw new Error('storage blocked')
    },
    setItem: () => {
      throw new Error('storage blocked')
    }
  }
}

describe('readLivePosition', () => {
  it('reads back the position written for that setlist', () => {
    const storage = fakeStorage()
    writeLivePosition(storage, SETLIST_ID, 11)

    assert.equal(readLivePosition(storage, SETLIST_ID, 25), 11)
  })

  it('keeps two setlists on separate positions', () => {
    const storage = fakeStorage()
    writeLivePosition(storage, SETLIST_ID, 11)
    writeLivePosition(storage, OTHER_SETLIST_ID, 3)

    assert.equal(readLivePosition(storage, SETLIST_ID, 25), 11)
    assert.equal(readLivePosition(storage, OTHER_SETLIST_ID, 25), 3)
  })

  it('starts at the first item when nothing was stored', () => {
    assert.equal(readLivePosition(fakeStorage(), SETLIST_ID, 25), 0)
  })

  // The setlist can lose items between the write and the read, and the last index of a 25 song set
  // is off the end of a 4 song one. Restoring it would render an empty screen.
  it('falls back to the first item when the stored index is past the end', () => {
    const storage = fakeStorage()
    writeLivePosition(storage, SETLIST_ID, 24)

    assert.equal(readLivePosition(storage, SETLIST_ID, 4), 0)
  })

  it('accepts the last item of the setlist', () => {
    const storage = fakeStorage()
    writeLivePosition(storage, SETLIST_ID, 3)

    assert.equal(readLivePosition(storage, SETLIST_ID, 4), 3)
  })

  it('falls back to the first item on an empty setlist', () => {
    const storage = fakeStorage()
    writeLivePosition(storage, SETLIST_ID, 2)

    assert.equal(readLivePosition(storage, SETLIST_ID, 0), 0)
  })

  it('refuses anything that is not a whole positive index', () => {
    const key = livePositionStorageKey(SETLIST_ID)

    for (const stored of ['', 'deux', '-1', '1.5', 'NaN', 'null']) {
      const storage = fakeStorage({ [key]: stored })
      assert.equal(readLivePosition(storage, SETLIST_ID, 25), 0, `stored: ${stored}`)
    }
  })

  it('falls back to the first item when there is no storage at all', () => {
    assert.equal(readLivePosition(null, SETLIST_ID, 25), 0)
  })

  it('falls back to the first item when the browser blocks storage', () => {
    assert.equal(readLivePosition(throwingStorage(), SETLIST_ID, 25), 0)
  })

  it('falls back to the first item when the setlist id is missing', () => {
    assert.equal(readLivePosition(fakeStorage(), null, 25), 0)
  })
})

describe('writeLivePosition', () => {
  it('stores the index under a key carrying the setlist id', () => {
    const storage = fakeStorage()
    writeLivePosition(storage, SETLIST_ID, 7)

    assert.equal(storage.values[livePositionStorageKey(SETLIST_ID)], '7')
  })

  it('does nothing when the browser blocks storage', () => {
    assert.doesNotThrow(() => writeLivePosition(throwingStorage(), SETLIST_ID, 7))
  })

  it('does nothing without a storage or a setlist id', () => {
    assert.doesNotThrow(() => writeLivePosition(null, SETLIST_ID, 7))

    const storage = fakeStorage()
    writeLivePosition(storage, null, 7)
    assert.deepEqual(storage.values, {})
  })

  it('refuses to store a nonsense index', () => {
    const storage = fakeStorage()
    writeLivePosition(storage, SETLIST_ID, -1)
    writeLivePosition(storage, SETLIST_ID, 1.5)
    writeLivePosition(storage, SETLIST_ID, Number.NaN)

    assert.deepEqual(storage.values, {})
  })
})

describe('openLivePositionStorage', () => {
  // No sessionStorage under the test runner, which is the same shape as a browser refusing it.
  it('returns null when there is no session storage', () => {
    assert.equal(openLivePositionStorage(), null)
  })
})
