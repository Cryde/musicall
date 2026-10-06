import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { userHandle } from './userHandle.js'

describe('userHandle', () => {
  it('shows the username next to a stage name', () => {
    assert.equal(userHandle('androidtest_123', 'Alex'), '@androidtest_123')
  })

  it('adds nothing without a stage name', () => {
    assert.equal(userHandle('bassiste', 'bassiste'), null)
    assert.equal(userHandle('bassiste', null), null)
    assert.equal(userHandle('bassiste', undefined), null)
  })

  it('never reveals the handle of a closed account', () => {
    assert.equal(userHandle('deleted_c7c9f2e1', 'Utilisateur supprimé'), null)
  })
})
