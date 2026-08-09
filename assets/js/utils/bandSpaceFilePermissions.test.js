import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { isFileCreatorOrAdmin } from './bandSpaceFilePermissions.js'

/**
 * The creator-or-admin rule the API enforces on delete and restore.
 *
 * Run with `npm test`.
 */

describe('isFileCreatorOrAdmin', () => {
  it('lets the uploader act on their own file', () => {
    assert.equal(isFileCreatorOrAdmin({ created_by: { id: 'user-1' } }, 'user-1', false), true)
  })

  it('lets an admin act on a file uploaded by somebody else', () => {
    assert.equal(isFileCreatorOrAdmin({ created_by: { id: 'user-1' } }, 'user-2', true), true)
  })

  it('lets an admin act on a file whose uploader is gone', () => {
    assert.equal(isFileCreatorOrAdmin({ created_by: null }, 'user-2', true), true)
  })

  it('refuses a plain member acting on somebody else file', () => {
    assert.equal(isFileCreatorOrAdmin({ created_by: { id: 'user-1' } }, 'user-2', false), false)
  })

  it('refuses when there is no file', () => {
    assert.equal(isFileCreatorOrAdmin(null, 'user-1', true), false)
    assert.equal(isFileCreatorOrAdmin(undefined, 'user-1', false), false)
  })

  it('refuses an anonymous visitor on a file with no uploader', () => {
    assert.equal(isFileCreatorOrAdmin({ created_by: null }, undefined, false), false)
    assert.equal(isFileCreatorOrAdmin({}, undefined, false), false)
  })
})
