import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { DELETED_DISPLAY_NAME, displayName } from './displayName.js'

describe('displayName', () => {
  it('prefers the name the API sends', () => {
    assert.equal(
      displayName({ username: 'androidtest_123', display_name: 'Alexandre Martin' }),
      'Alexandre Martin'
    )
  })

  it('falls back to the username when the payload carries no name', () => {
    assert.equal(displayName({ username: 'androidtest_123' }), 'androidtest_123')
  })

  it('labels a closed account', () => {
    assert.equal(
      displayName({ username: 'deleted_c7c9f2e1', deletion_datetime: '2026-06-01T09:00:00+00:00' }),
      DELETED_DISPLAY_NAME
    )
  })
})
