import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { withVersion } from './fileVersionUrl.js'

describe('withVersion', () => {
  it('adds the version as the first query parameter', () => {
    assert.equal(withVersion('/api/files/1/download', 'v-2'), '/api/files/1/download?v=v-2')
  })

  it('adds it after a query the URL already has', () => {
    assert.equal(
      withVersion('/api/files/1/download?inline=1', 'v-2'),
      '/api/files/1/download?inline=1&v=v-2'
    )
  })

  it('leaves a URL without a version as it is', () => {
    assert.equal(withVersion('/api/files/1/download', null), '/api/files/1/download')
  })
})
