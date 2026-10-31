import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { quotaSeverity } from './adminBandSpace.js'

describe('quotaSeverity', () => {
  it('is quiet well under the quota', () => {
    assert.equal(quotaSeverity(1000, 10000), 'secondary')
  })

  it('warns from 80 % of the quota, where the members are warned too', () => {
    assert.equal(quotaSeverity(7999, 10000), 'secondary')
    assert.equal(quotaSeverity(8000, 10000), 'warn')
  })

  it('is a danger once the quota is reached', () => {
    assert.equal(quotaSeverity(10000, 10000), 'danger')
    assert.equal(quotaSeverity(12000, 10000), 'danger')
  })

  it('is a danger on a zero quota rather than dividing by it', () => {
    assert.equal(quotaSeverity(0, 0), 'danger')
  })
})
