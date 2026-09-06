import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  grantsAdmin,
  grantsRole,
  grantsTester,
  ROLE_ADMIN,
  ROLE_TESTER,
  ROLE_USER
} from './roles.js'

describe('grantsAdmin', () => {
  it('accepts an admin', () => {
    assert.equal(grantsAdmin([ROLE_ADMIN, ROLE_USER]), true)
  })

  it('refuses an ordinary user', () => {
    assert.equal(grantsAdmin([ROLE_USER]), false)
  })

  // #942: the flag must not be a rank. A tester is an ordinary account, so handing someone a
  // preview must not hand them the back office, which is exactly what its predecessor did.
  it('refuses a tester, who is an ordinary user with a flag', () => {
    assert.equal(grantsAdmin([ROLE_TESTER, ROLE_USER]), false)
  })
})

describe('grantsTester', () => {
  it('accepts a tester', () => {
    assert.equal(grantsTester([ROLE_TESTER, ROLE_USER]), true)
  })

  it('refuses an ordinary user', () => {
    assert.equal(grantsTester([ROLE_USER]), false)
  })

  // The other half of keeping the flag orthogonal: being an admin does not enrol you in previews.
  // ROLE_SUPER_ADMIN used to grant both directions at once, which is why neither now does.
  it('refuses an admin who is not also a tester', () => {
    assert.equal(grantsTester([ROLE_ADMIN, ROLE_USER]), false)
  })
})

describe('grantsRole', () => {
  it('walks the hierarchy downwards', () => {
    assert.equal(grantsRole([ROLE_ADMIN], ROLE_USER), true)
  })

  it('does not walk it upwards', () => {
    assert.equal(grantsRole([ROLE_USER], ROLE_ADMIN), false)
  })

  // The guard runs before the store has necessarily settled, and an expired or malformed token
  // leaves `user` null, so none of these may throw.
  it('treats a missing or malformed role list as granting nothing', () => {
    for (const value of [null, undefined, 'ROLE_ADMIN', {}, 42, [null], [undefined], [{}]]) {
      assert.equal(grantsAdmin(value), false, `${JSON.stringify(value)} must not grant admin`)
    }
  })

  it('tolerates a role that is not in the hierarchy at all', () => {
    assert.equal(grantsAdmin(['ROLE_SOMETHING_ELSE']), false)
    assert.equal(grantsRole(['ROLE_SOMETHING_ELSE'], 'ROLE_SOMETHING_ELSE'), true)
  })
})
