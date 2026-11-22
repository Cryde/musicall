import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { orderedSpecialities } from './servicePage.js'

describe('orderedSpecialities', () => {
  it('keeps the kind order and drops what the kind does not know', () => {
    assert.deepEqual(
      orderedSpecialities(['mixing', 'mastering', 'editing'], ['editing', 'nope', 'mixing']),
      ['mixing', 'editing']
    )
    assert.deepEqual(orderedSpecialities(['concert', 'studio'], undefined), [])
  })
})
