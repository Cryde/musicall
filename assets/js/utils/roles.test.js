import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  grantsAdmin,
  grantsRole,
  grantsSuperAdmin,
  ROLE_ADMIN,
  ROLE_SUPER_ADMIN,
  ROLE_USER
} from './roles.js'

describe('grantsAdmin', () => {
  it('accepts an admin', () => {
    assert.equal(grantsAdmin([ROLE_ADMIN, ROLE_USER]), true)
  })

  // The whole point of #940. Roles arrive unexpanded from both the JWT and /api/users/self, so a
  // super admin carries no ROLE_ADMIN and a plain includes() locked the most privileged account on
  // the site out of the back office.
  it('accepts a super admin, who never carries ROLE_ADMIN', () => {
    assert.equal([ROLE_SUPER_ADMIN, ROLE_USER].includes(ROLE_ADMIN), false)
    assert.equal(grantsAdmin([ROLE_SUPER_ADMIN, ROLE_USER]), true)
  })

  it('refuses an ordinary user', () => {
    assert.equal(grantsAdmin([ROLE_USER]), false)
  })
})

describe('grantsSuperAdmin', () => {
  it('accepts only a super admin', () => {
    assert.equal(grantsSuperAdmin([ROLE_SUPER_ADMIN]), true)
    assert.equal(grantsSuperAdmin([ROLE_ADMIN, ROLE_USER]), false)
    assert.equal(grantsSuperAdmin([ROLE_USER]), false)
  })
})

describe('grantsRole', () => {
  it('walks the hierarchy transitively', () => {
    assert.equal(grantsRole([ROLE_SUPER_ADMIN], ROLE_USER), true)
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
