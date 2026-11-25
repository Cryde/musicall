import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { movedIds, orderedBy } from './listOrder.js'

describe('movedIds', () => {
  it('moves an item one step', () => {
    assert.deepEqual(movedIds(['a', 'b', 'c'], 'b', -1), ['b', 'a', 'c'])
    assert.deepEqual(movedIds(['a', 'b', 'c'], 'b', 1), ['a', 'c', 'b'])
  })

  it('refuses a move off either end', () => {
    assert.equal(movedIds(['a', 'b'], 'a', -1), null)
    assert.equal(movedIds(['a', 'b'], 'b', 1), null)
  })

  it('refuses an item that is not in the list', () => {
    assert.equal(movedIds(['a', 'b'], 'z', 1), null)
  })
})

describe('orderedBy', () => {
  it('follows the order sent', () => {
    const items = [{ id: 'a' }, { id: 'b' }, { id: 'c' }]
    assert.deepEqual(orderedBy(items, ['c', 'a', 'b']), [{ id: 'c' }, { id: 'a' }, { id: 'b' }])
  })
})
