import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { latestOnly } from './latestOnly.js'

function deferredFetch() {
  const pending = []
  const fetch = () =>
    new Promise((resolve, reject) => {
      pending.push({ resolve, reject })
    })
  return { fetch, pending }
}

describe('latestOnly', () => {
  it('settles a lone call with its own value', async () => {
    const { fetch, pending } = deferredFetch()
    const load = latestOnly(fetch)

    const call = load()
    pending[0].resolve(['a'])

    assert.deepEqual(await call, ['a'])
  })

  it('settles an older call with the newer one, even when the older answers first', async () => {
    const { fetch, pending } = deferredFetch()
    const load = latestOnly(fetch)

    const older = load()
    const newer = load()
    pending[0].resolve(['stale'])
    pending[1].resolve(['fresh'])

    assert.deepEqual(await older, ['fresh'])
    assert.deepEqual(await newer, ['fresh'])
  })

  it('does not fail an older call for its own error once a newer one exists', async () => {
    const { fetch, pending } = deferredFetch()
    const load = latestOnly(fetch)

    const older = load()
    const newer = load()
    pending[0].reject(new Error('older failed'))
    pending[1].resolve(['fresh'])

    assert.deepEqual(await older, ['fresh'])
    assert.deepEqual(await newer, ['fresh'])
  })

  it('fails every waiting call when the newest one fails', async () => {
    const { fetch, pending } = deferredFetch()
    const load = latestOnly(fetch)

    const older = load()
    const newer = load()
    pending[0].resolve(['stale'])
    pending[1].reject(new Error('newest failed'))

    await assert.rejects(older, /newest failed/)
    await assert.rejects(newer, /newest failed/)
  })

  it('starts afresh once everything has settled', async () => {
    const { fetch, pending } = deferredFetch()
    const load = latestOnly(fetch)

    const first = load()
    pending[0].resolve(['one'])
    await first
    const second = load()
    pending[1].resolve(['two'])

    assert.deepEqual(await second, ['two'])
  })
})
