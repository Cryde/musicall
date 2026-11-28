import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { experienceStartYearRange } from './experienceStartYear.js'

describe('experienceStartYearRange', () => {
  it('runs from 70 years back to the current year', () => {
    assert.deepEqual(experienceStartYearRange(new Date(2026, 5, 15)), { min: 1956, max: 2026 })
  })

  it('follows the calendar year', () => {
    assert.deepEqual(experienceStartYearRange(new Date(2027, 0, 1)), { min: 1957, max: 2027 })
  })
})
