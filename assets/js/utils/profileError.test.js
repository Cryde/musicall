import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  isPrivateProfileError,
  isSameUsername,
  PRIVATE_PROFILE_ERROR_TYPE
} from './profileError.js'

const failure = (status, data) => ({ response: { status, data } })

describe('isPrivateProfileError', () => {
  it('is true for the private profile problem type', () => {
    assert.equal(isPrivateProfileError(failure(404, { type: PRIVATE_PROFILE_ERROR_TYPE })), true)
  })

  it('is false for a profile that does not exist', () => {
    assert.equal(isPrivateProfileError(failure(404, { type: '/errors/404' })), false)
  })

  it('is false for another status carrying the type', () => {
    assert.equal(isPrivateProfileError(failure(500, { type: PRIVATE_PROFILE_ERROR_TYPE })), false)
  })

  it('is false for a network error or nothing at all', () => {
    assert.equal(isPrivateProfileError(new Error('Network Error')), false)
    assert.equal(isPrivateProfileError(failure(404, undefined)), false)
    assert.equal(isPrivateProfileError(undefined), false)
  })
})

describe('isSameUsername', () => {
  it('ignores case', () => {
    assert.equal(isSameUsername('Cryde', 'cryde'), true)
  })

  it('is false for different names or a missing one', () => {
    assert.equal(isSameUsername('cryde', 'cryd'), false)
    assert.equal(isSameUsername('cryde', undefined), false)
    assert.equal(isSameUsername(null, null), false)
  })
})
