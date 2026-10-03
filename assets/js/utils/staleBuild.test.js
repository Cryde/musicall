import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  isChunkLoadError,
  RELOAD_GUARD_MS,
  reloadOnceForStaleBuild,
  STALE_BUILD_RELOAD_KEY
} from './staleBuild.js'

/**
 * A stale tab after a deploy (#1105). Run with `npm test`.
 */

function memoryStorage(initial = {}) {
  const values = { ...initial }
  return {
    getItem: (key) => values[key] ?? null,
    setItem: (key, value) => {
      values[key] = value
    },
    values
  }
}

describe('isChunkLoadError', () => {
  it('recognises a failed dynamic import in each browser', () => {
    for (const message of [
      'Failed to fetch dynamically imported module: https://musicall.com/build/assets/Agenda-abc.js',
      'error loading dynamically imported module: https://musicall.com/build/assets/Agenda-abc.js',
      'Importing a module script failed.',
      "'text/html' is not a valid JavaScript MIME type.",
      'Unable to preload CSS for /build/assets/Agenda-abc.css'
    ]) {
      assert.equal(isChunkLoadError(new Error(message)), true, message)
    }
  })

  it('reads a rejection that is a bare message rather than an Error', () => {
    assert.equal(
      isChunkLoadError('Failed to fetch dynamically imported module: /build/assets/Agenda-abc.js'),
      true
    )
  })

  it('leaves any other error alone', () => {
    assert.equal(isChunkLoadError(new Error('Request failed with status code 500')), false)
    assert.equal(isChunkLoadError(null), false)
  })
})

describe('reloadOnceForStaleBuild', () => {
  it('reloads to the address asked for and remembers when', () => {
    const storage = memoryStorage()
    const loads = []

    const reloaded = reloadOnceForStaleBuild({
      storage,
      now: () => 50_000,
      load: (url) => loads.push(url),
      url: '/agenda'
    })

    assert.equal(reloaded, true)
    assert.deepEqual(loads, ['/agenda'])
    assert.equal(storage.values[STALE_BUILD_RELOAD_KEY], '50000')
  })

  it('does not reload again within the guard window, so a real outage cannot loop', () => {
    const storage = memoryStorage({ [STALE_BUILD_RELOAD_KEY]: '50000' })
    const loads = []

    const reloaded = reloadOnceForStaleBuild({
      storage,
      now: () => 50_000 + RELOAD_GUARD_MS - 1,
      load: (url) => loads.push(url)
    })

    assert.equal(reloaded, false)
    assert.deepEqual(loads, [])
  })

  it('reloads again for a later deploy, once the window has passed', () => {
    const storage = memoryStorage({ [STALE_BUILD_RELOAD_KEY]: '50000' })
    const loads = []

    const reloaded = reloadOnceForStaleBuild({
      storage,
      now: () => 50_000 + RELOAD_GUARD_MS,
      load: (url) => loads.push(url)
    })

    assert.equal(reloaded, true)
  })

  it('does not reload when storage is blocked, as nothing could stop a loop', () => {
    const storage = {
      getItem: () => {
        throw new Error('SecurityError')
      },
      setItem: () => {}
    }
    const loads = []

    assert.equal(
      reloadOnceForStaleBuild({ storage, now: () => 1, load: (url) => loads.push(url) }),
      false
    )
    assert.deepEqual(loads, [])
  })
})
