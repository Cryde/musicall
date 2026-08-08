import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  FILES_PAGE_SIZE,
  fileCountLabel,
  hasMoreToLoad,
  mergePage,
  nextPageToLoad,
  queryKeyOf
} from './filePagination.js'

/**
 * These pin the paging of the band space file list, and above all of its trash: the list stopped at
 * the first 50 rows with no paginator and no count, so a band with more than 50 files in the trash
 * could not reach the older ones before app:band-space:purge destroyed them.
 *
 * Run with `npm test`. Node's own runner, so this costs no dependency. The Vue wiring itself still
 * has no coverage, because that would need jsdom and a component harness.
 */

/** A file row as the collection returns it, reduced to what the paging cares about. */
function row(id, name = id) {
  return { id, original_name: name }
}

/** `count` rows named r0, r1, ... */
function rows(count, prefix = 'r') {
  return Array.from({ length: count }, (_, index) => row(`${prefix}${index}`))
}

describe('nextPageToLoad', () => {
  it('asks for the first page when nothing is loaded', () => {
    assert.equal(nextPageToLoad(0), 1)
  })

  it('asks for the second page once a full page is held', () => {
    assert.equal(nextPageToLoad(FILES_PAGE_SIZE), 2)
  })

  it('keeps asking for the same page across a partial page', () => {
    assert.equal(nextPageToLoad(1), 1)
    assert.equal(nextPageToLoad(FILES_PAGE_SIZE - 1), 1)
  })

  it('counts pages from the rows held, not from a click counter', () => {
    assert.equal(nextPageToLoad(2 * FILES_PAGE_SIZE), 3)
    assert.equal(nextPageToLoad(2 * FILES_PAGE_SIZE + 1), 3)
  })

  // The trash case. Restoring a file removes it from the server window as well as from the list, so
  // page 2 would start one row further along than it should and step over a file for good.
  it('steps back to the page holding the gap when a loaded row is removed', () => {
    assert.equal(nextPageToLoad(FILES_PAGE_SIZE - 1), 1)
    assert.equal(nextPageToLoad(2 * FILES_PAGE_SIZE - 3), 2)
  })

  it('honours a page size of its own', () => {
    assert.equal(nextPageToLoad(0, 10), 1)
    assert.equal(nextPageToLoad(9, 10), 1)
    assert.equal(nextPageToLoad(10, 10), 2)
    assert.equal(nextPageToLoad(25, 10), 3)
  })

  it('never divides by a page size of zero', () => {
    assert.equal(nextPageToLoad(40, 0), 1)
    assert.equal(nextPageToLoad(40, -5), 1)
  })

  it('treats a negative row count as an empty list', () => {
    assert.equal(nextPageToLoad(-3), 1)
  })
})

describe('hasMoreToLoad', () => {
  it('sees the rest of a truncated collection', () => {
    assert.equal(hasMoreToLoad(50, 128), true)
  })

  it('stops once every row is held', () => {
    assert.equal(hasMoreToLoad(128, 128), false)
  })

  it('stops on an empty collection', () => {
    assert.equal(hasMoreToLoad(0, 0), false)
  })

  // A total that lags behind after several local removals must not offer a page that is not there.
  it('stops when more rows are held than the total claims', () => {
    assert.equal(hasMoreToLoad(51, 50), false)
  })
})

describe('mergePage', () => {
  it('appends a page of unseen rows in order', () => {
    const merged = mergePage([row('a'), row('b')], [row('c'), row('d')])

    assert.deepEqual(
      merged.map((item) => item.id),
      ['a', 'b', 'c', 'd']
    )
  })

  it('keeps a row that arrives twice once', () => {
    const merged = mergePage([row('a'), row('b')], [row('b'), row('c')])

    assert.deepEqual(
      merged.map((item) => item.id),
      ['a', 'b', 'c']
    )
  })

  it('refreshes a known row in place rather than moving it to the end', () => {
    const merged = mergePage(
      [row('a', 'old.pdf'), row('b', 'b.pdf')],
      [row('a', 'renamed.pdf'), row('c', 'c.pdf')]
    )

    assert.deepEqual(
      merged.map((item) => item.original_name),
      ['renamed.pdf', 'b.pdf', 'c.pdf']
    )
  })

  it('leaves both inputs untouched', () => {
    const loaded = [row('a')]
    const incoming = [row('b')]

    mergePage(loaded, incoming)

    assert.equal(loaded.length, 1)
    assert.equal(incoming.length, 1)
  })

  it('returns the loaded rows when the page comes back empty', () => {
    assert.deepEqual(
      mergePage([row('a')], []).map((item) => item.id),
      ['a']
    )
  })
})

describe('queryKeyOf', () => {
  it('ignores the page, so pages of one query share a key', () => {
    assert.equal(queryKeyOf({ folderId: 'f1', page: 1 }), queryKeyOf({ folderId: 'f1', page: 4 }))
  })

  it('ignores the order the parameters were built in', () => {
    assert.equal(
      queryKeyOf({ query: 'master', sort: 'name', order: 'asc' }),
      queryKeyOf({ order: 'asc', sort: 'name', query: 'master' })
    )
  })

  // The reset on filter change case: every one of these must retarget the list.
  it('moves when the folder changes', () => {
    assert.notEqual(queryKeyOf({ folderId: 'f1' }), queryKeyOf({ folderId: 'f2' }))
  })

  it('moves when the search text changes', () => {
    assert.notEqual(queryKeyOf({ query: 'master' }), queryKeyOf({ query: 'rider' }))
  })

  it('moves when the sort changes', () => {
    assert.notEqual(
      queryKeyOf({ sort: 'date', order: 'desc' }),
      queryKeyOf({ sort: 'date', order: 'asc' })
    )
  })

  it('moves when a filter is added', () => {
    assert.notEqual(queryKeyOf({ sort: 'date' }), queryKeyOf({ sort: 'date', tagId: 't1' }))
  })

  it('moves between the live files and the trash', () => {
    assert.notEqual(queryKeyOf({ sort: 'date' }), queryKeyOf({ archived: true }))
  })

  it('treats a cleared filter as no filter at all', () => {
    assert.equal(queryKeyOf({ tagId: null }), queryKeyOf({ tagId: undefined }))
  })
})

describe('fileCountLabel', () => {
  it('says nothing about an empty collection', () => {
    assert.equal(fileCountLabel(0, 0), '')
  })

  it('gives the plain total once everything is on screen', () => {
    assert.equal(fileCountLabel(128, 128), '128 fichiers')
  })

  it('agrees in the singular', () => {
    assert.equal(fileCountLabel(1, 1), '1 fichier')
  })

  it('admits how much of the collection is missing', () => {
    assert.equal(fileCountLabel(50, 128), '50 fichiers affichés sur 128')
  })

  it('agrees in the singular on a single loaded row', () => {
    assert.equal(fileCountLabel(1, 40), '1 fichier affiché sur 40')
  })
})

/**
 * The pieces working together, over the sequences that used to lose files. Each step is exactly
 * what the store does: pick a page from the rows held, merge the answer, then ask whether anything
 * is left.
 */
describe('paging a mutating collection', () => {
  /** The server view of an ordered collection, answering an offset page like the API does. */
  function serverPage(all, page, pageSize = FILES_PAGE_SIZE) {
    const offset = (page - 1) * pageSize

    return all.slice(offset, offset + pageSize)
  }

  it('walks a stable collection to the end without repeating or dropping a row', () => {
    const all = rows(128)
    let loaded = serverPage(all, 1)

    while (hasMoreToLoad(loaded.length, all.length)) {
      loaded = mergePage(loaded, serverPage(all, nextPageToLoad(loaded.length)))
    }

    assert.deepEqual(
      loaded.map((item) => item.id),
      all.map((item) => item.id)
    )
  })

  // The bug that made this feature necessary, reproduced from the trash: restore files off the
  // first page and every file behind the window must still be reachable.
  it('reaches every file after rows are restored out of the loaded page', () => {
    const server = rows(120)
    let loaded = serverPage(server, 1)
    let total = server.length

    // Restore the first three rows: gone from the server list and from the loaded rows alike.
    for (const restoredId of ['r0', 'r1', 'r2']) {
      server.splice(
        server.findIndex((item) => item.id === restoredId),
        1
      )
      loaded = loaded.filter((item) => item.id !== restoredId)
      total -= 1
    }

    assert.equal(loaded.length, FILES_PAGE_SIZE - 3)
    // Back to page 1, which is where the gap the removals opened now sits.
    assert.equal(nextPageToLoad(loaded.length), 1)

    while (hasMoreToLoad(loaded.length, total)) {
      loaded = mergePage(loaded, serverPage(server, nextPageToLoad(loaded.length)))
    }

    assert.deepEqual(
      loaded.map((item) => item.id),
      server.map((item) => item.id)
    )
  })

  it('reaches every file after one is uploaded ahead of the loaded page', () => {
    const server = rows(120)
    let loaded = serverPage(server, 1)
    let total = server.length

    // An upload lands at the top of a date descending list, in the store and on the server alike.
    const uploaded = row('fresh')
    server.unshift(uploaded)
    loaded = [uploaded, ...loaded]
    total += 1

    while (hasMoreToLoad(loaded.length, total)) {
      loaded = mergePage(loaded, serverPage(server, nextPageToLoad(loaded.length)))
    }

    assert.deepEqual(
      loaded.map((item) => item.id),
      server.map((item) => item.id)
    )
  })

  // The classic reset on filter change bug: a further page resolving after the user changed a
  // filter must not be merged into the list, which no longer holds the rows that page continues.
  it('drops a page that describes the query the user just left', () => {
    const inFlight = { sort: 'date', order: 'desc', page: 2 }
    const inFlightKey = queryKeyOf(inFlight)

    // The user opens a folder while page 2 of the root listing is still in flight, so the list is
    // replaced by the first page of the folder and the key it belongs to moves with it.
    let loaded = rows(3, 'folder-')
    const loadedKey = queryKeyOf({ sort: 'date', order: 'desc', folderId: 'f1', page: 1 })

    const applyLatePage = (page) => {
      if (inFlightKey !== loadedKey) {
        return
      }
      loaded = mergePage(loaded, page)
    }
    applyLatePage(rows(50, 'root-'))

    assert.deepEqual(
      loaded.map((item) => item.id),
      ['folder-0', 'folder-1', 'folder-2']
    )
  })

  /**
   * The limit of the derived page maths, pinned so the store's resync cannot be quietly dropped as
   * redundant later. Another member restoring an old file inserts it behind the window rather than
   * at the front, so the loaded count stops growing, the derived page stops moving with it, and
   * clicking again re-requests the same offset from then on. The store watches for exactly this,
   * a page bringing nothing new while more is still reported, and rebuilds from the first page.
   */
  it('stalls on a row inserted behind the window, which is why the store resyncs', () => {
    const server = rows(120)
    let loaded = serverPage(server, 1)
    let total = server.length

    server.splice(40, 0, row('inserted-behind-the-window'))
    total += 1

    let productiveRequests = 0
    for (let i = 0; i < 5 && hasMoreToLoad(loaded.length, total); i++) {
      const heldBefore = loaded.length
      loaded = mergePage(loaded, serverPage(server, nextPageToLoad(loaded.length)))
      if (loaded.length === heldBefore) break
      productiveRequests++
    }

    assert.equal(productiveRequests, 1, 'one page lands, then the offset repeats forever')
    assert.equal(
      loaded.some((item) => item.id === 'inserted-behind-the-window'),
      false,
      'the inserted row cannot be reached by paging alone'
    )
    assert.equal(hasMoreToLoad(loaded.length, total), true, 'while the server still reports more')
  })
})
