import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  flattenGroups,
  groupResultsByType,
  moveActiveIndex,
  routeForResult,
  SEARCH_TYPES
} from './bandSpaceSearch.js'

function result(type, resourceId) {
  return { type, resource_id: resourceId, title: `${type}-${resourceId}` }
}

describe('groupResultsByType', () => {
  it('groups in SEARCH_TYPES order whatever order the results arrive in', () => {
    const groups = groupResultsByType([result('finance', '1'), result('agenda', '2')])

    assert.deepEqual(
      groups.map((group) => group.type),
      ['agenda', 'finance']
    )
  })

  it('keeps several results of the same type together', () => {
    const groups = groupResultsByType([
      result('song', '1'),
      result('note', '2'),
      result('song', '3')
    ])

    assert.deepEqual(
      groups.map((group) => group.type),
      ['note', 'song']
    )
    assert.deepEqual(
      groups[1].results.map((r) => r.resource_id),
      ['1', '3']
    )
  })

  it('drops empty groups rather than rendering an empty heading', () => {
    assert.deepEqual(groupResultsByType([]), [])
    assert.equal(groupResultsByType([result('task', '1')]).length, 1)
  })

  it('carries the label and icon the palette renders', () => {
    const [group] = groupResultsByType([result('song', '1')])

    assert.equal(group.label, 'Morceaux')
    assert.equal(group.icon, 'pi-play')
  })
})

describe('flattenGroups', () => {
  it('flattens back into rendered order', () => {
    const groups = groupResultsByType([
      result('song', '1'),
      result('agenda', '2'),
      result('song', '3')
    ])

    assert.deepEqual(
      flattenGroups(groups).map((r) => r.resource_id),
      ['2', '1', '3']
    )
  })

  it('returns nothing for no groups', () => {
    assert.deepEqual(flattenGroups([]), [])
  })
})

describe('moveActiveIndex', () => {
  it('moves down and up', () => {
    assert.equal(moveActiveIndex(3, 0, 1), 1)
    assert.equal(moveActiveIndex(3, 2, -1), 1)
  })

  it('wraps from the last row to the first', () => {
    assert.equal(moveActiveIndex(3, 2, 1), 0)
  })

  // A bare modulo returns -1 here, which is the whole reason this is a function.
  it('wraps from the first row to the last rather than going negative', () => {
    assert.equal(moveActiveIndex(3, 0, -1), 2)
  })

  it('reports no active row when the list is empty', () => {
    assert.equal(moveActiveIndex(0, -1, 1), -1)
    assert.equal(moveActiveIndex(0, -1, -1), -1)
  })
})

describe('routeForResult', () => {
  it('builds a location for every declared type', () => {
    for (const { type, route, param } of SEARCH_TYPES) {
      assert.deepEqual(routeForResult(result(type, 'abc'), 'space-1'), {
        name: route,
        params: { id: 'space-1' },
        query: { [param]: 'abc' }
      })
    }
  })

  it('sends a song to the setlist module, not to a module of its own', () => {
    const song = routeForResult(result('song', 'abc'), 'space-1')
    const setlist = routeForResult(result('setlist', 'def'), 'space-1')

    assert.equal(song.name, setlist.name)
    assert.deepEqual(song.query, { song: 'abc' })
  })

  it('returns null for a type the palette does not know', () => {
    assert.equal(routeForResult(result('rider', 'abc'), 'space-1'), null)
    assert.equal(routeForResult(undefined, 'space-1'), null)
  })
})
