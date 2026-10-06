import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { memberHandle } from './memberHandle.js'

describe('memberHandle', () => {
  it('shows the username next to a stage name', () => {
    assert.equal(memberHandle('androidtest_123', 'Alex'), '@androidtest_123')
  })

  it('adds nothing without a stage name', () => {
    assert.equal(memberHandle('bassiste', 'bassiste'), null)
    assert.equal(memberHandle('bassiste', null), null)
    assert.equal(memberHandle('bassiste', undefined), null)
  })

  it('never reveals the handle of a closed account', () => {
    assert.equal(memberHandle('deleted_c7c9f2e1', 'Utilisateur supprimé'), null)
  })
})
