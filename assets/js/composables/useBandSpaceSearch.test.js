import assert from 'node:assert/strict'
import { after, beforeEach, describe, it } from 'node:test'
import { nextTick } from 'vue'
import bandSpaceSearchApi from '../api/bandSpace/band-space-search.js'
import { MIN_SEARCH_QUERY_LENGTH, useBandSpaceSearch } from './useBandSpaceSearch.js'

/**
 * The search state machine the command palette and the chat attachment picker share. It was inside
 * CommandPalette's `<script setup>` until #971 and therefore untestable, this project having no
 * component mount infrastructure; extracting it is what made these possible, and the race guard is
 * exactly the part nothing else would catch a regression in.
 *
 * The api module is a default exported object and the composable reads `.search` off it at call
 * time, so swapping the property is enough: no module mocking, no flag on `node --test`.
 *
 * Run with `npm test`.
 */

const BAND_SPACE_ID = 'space-1'
/** Comfortably past the composable's 250ms debounce. */
const PAST_DEBOUNCE_MS = 350

const realSearch = bandSpaceSearchApi.search

after(() => {
  bandSpaceSearchApi.search = realSearch
})

function hit(type, resourceId, title = `${type} ${resourceId}`) {
  return { id: `${type}-${resourceId}`, type, resource_id: resourceId, title, subtitle: null }
}

function wait(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

/** Every term the composable actually asked the server for, in order. */
let askedFor = []
/** The kind sent with each of those calls, null for every kind. */
let askedForType = []

beforeEach(() => {
  askedFor = []
  askedForType = []
  bandSpaceSearchApi.search = async (_bandSpaceId, term, type = null) => {
    askedFor.push(term)
    askedForType.push(type)

    return term === '' ? [hit('song', 'recent'), hit('task', 'recent')] : [hit('task', term)]
  }
})

function search() {
  return useBandSpaceSearch(() => BAND_SPACE_ID, 'listbox')
}

describe('useBandSpaceSearch', () => {
  it('does not ask the server for a term below the minimum length', async () => {
    const state = search()

    state.query.value = 'a'.repeat(MIN_SEARCH_QUERY_LENGTH - 1)
    await wait(PAST_DEBOUNCE_MS)

    // Only the recents, which an empty or short box shows (#1046): never the short term itself.
    assert.deepEqual(askedFor, [''])
    assert.deepEqual(state.groups.value, [])
    assert.equal(state.hasSearched.value, false)
    assert.equal(state.isSearching.value, false)
  })

  it('asks once for the last term typed rather than once per keystroke', async () => {
    const state = search()

    state.query.value = 'co'
    state.query.value = 'con'
    state.query.value = 'conc'
    await wait(PAST_DEBOUNCE_MS)

    assert.deepEqual(askedFor, ['conc'])
    assert.deepEqual(
      state.flatResults.value.map((result) => result.id),
      ['task-conc']
    )
    assert.equal(state.hasSearched.value, true)
  })

  it('raises the spinner as soon as a long enough term is typed, before the debounce elapses', async () => {
    const state = search()

    state.query.value = 'concert'
    await nextTick()

    assert.equal(state.isSearching.value, true)
    assert.deepEqual(askedFor, [])

    // Waited out rather than left pending: the call would otherwise land in the middle of the next
    // test and count against its expectations.
    await wait(PAST_DEBOUNCE_MS)
    assert.deepEqual(askedFor, ['concert'])
  })

  it('drops a response that lands after a newer term has already answered', async () => {
    let answerTheFirst = null
    bandSpaceSearchApi.search = (_bandSpaceId, term) => {
      askedFor.push(term)
      if (term === 'co') {
        return new Promise((resolve) => {
          answerTheFirst = () => resolve([hit('note', 'stale')])
        })
      }

      return Promise.resolve([hit('task', 'fresh')])
    }

    const state = search()

    state.query.value = 'co'
    await wait(PAST_DEBOUNCE_MS)
    state.query.value = 'conc'
    await wait(PAST_DEBOUNCE_MS)

    assert.deepEqual(askedFor, ['co', 'conc'])
    assert.deepEqual(
      state.flatResults.value.map((result) => result.id),
      ['task-fresh']
    )

    answerTheFirst()
    await wait(20)

    assert.deepEqual(
      state.flatResults.value.map((result) => result.id),
      ['task-fresh']
    )
    assert.equal(state.isSearching.value, false)
  })

  it('reports a failed search and shows nothing rather than the previous results', async () => {
    const state = search()

    state.query.value = 'concert'
    await wait(PAST_DEBOUNCE_MS)
    assert.equal(state.flatResults.value.length, 1)

    bandSpaceSearchApi.search = async () => {
      throw new Error('Erreur serveur')
    }
    state.query.value = 'concerts'
    await wait(PAST_DEBOUNCE_MS)

    assert.equal(state.searchError.value, 'Erreur serveur')
    assert.deepEqual(state.groups.value, [])
    assert.equal(state.isSearching.value, false)
    assert.equal(state.hasSearched.value, true)
  })

  it('moves the active row through the flattened list, wrapping at both ends', async () => {
    bandSpaceSearchApi.search = async () => [
      hit('agenda', 'one'),
      hit('task', 'two'),
      hit('note', 'three')
    ]

    const state = search()
    state.query.value = 'concert'
    await wait(PAST_DEBOUNCE_MS)

    assert.equal(state.activeResult.value.id, 'agenda-one')
    assert.equal(state.activeOptionId.value, 'listbox-option-agenda-one')

    state.moveActive(1)
    assert.equal(state.activeResult.value.id, 'task-two')

    state.moveActive(-1)
    state.moveActive(-1)
    assert.equal(state.activeResult.value.id, 'note-three')
  })

  it('follows the row the pointer is on', async () => {
    bandSpaceSearchApi.search = async () => [hit('agenda', 'one'), hit('task', 'two')]

    const state = search()
    state.query.value = 'concert'
    await wait(PAST_DEBOUNCE_MS)

    state.setActiveResult(state.flatResults.value[1])

    assert.equal(state.activeResult.value.id, 'task-two')
  })

  it('reset empties everything, so reopening never shows the previous search', async () => {
    const state = search()

    state.query.value = 'concert'
    await wait(PAST_DEBOUNCE_MS)
    state.moveActive(1)

    state.reset()

    assert.equal(state.query.value, '')
    assert.deepEqual(state.groups.value, [])
    assert.equal(state.searchError.value, null)
    assert.equal(state.hasSearched.value, false)
    assert.equal(state.isSearching.value, false)
    assert.equal(state.activeResult.value, null)
    assert.equal(state.activeOptionId.value, undefined)
  })

  it('drops a response that was in flight when reset ran', async () => {
    let answer = null
    bandSpaceSearchApi.search = (_bandSpaceId, term) => {
      askedFor.push(term)

      return new Promise((resolve) => {
        answer = () => resolve([hit('task', 'late')])
      })
    }

    const state = search()
    state.query.value = 'concert'
    await wait(PAST_DEBOUNCE_MS)

    state.reset()
    answer()
    await wait(20)

    assert.deepEqual(state.groups.value, [])
    assert.equal(state.hasSearched.value, false)
  })
})

describe('useBandSpaceSearch recents and kind filter (#1046)', () => {
  it('opens on the recent items, flat and in the order the server sent them', async () => {
    const state = search()

    state.reset()
    await wait(10)

    assert.deepEqual(askedFor, [''])
    assert.deepEqual(
      state.flatResults.value.map((result) => result.id),
      ['song-recent', 'task-recent']
    )
    assert.equal(state.activeResult.value?.id, 'song-recent')
  })

  it('reloads the recents of the picked kind while nothing is typed', async () => {
    const state = search()

    state.toggleType('song')
    await wait(10)

    assert.deepEqual(askedFor, [''])
    assert.deepEqual(askedForType, ['song'])
    assert.equal(state.selectedType.value, 'song')
  })

  it('searches again straight away, for the picked kind only, once a term is typed', async () => {
    const state = search()
    state.query.value = 'mix'
    await wait(PAST_DEBOUNCE_MS)

    state.toggleType('task')
    await wait(10)

    assert.deepEqual(askedFor, ['mix', 'mix'])
    assert.deepEqual(askedForType, [null, 'task'])
  })

  it('goes back to every kind when the picked one is picked again', () => {
    const state = search()

    state.toggleType('task')
    state.toggleType('task')

    assert.equal(state.selectedType.value, null)
  })

  it('never lets a late recents answer land on top of the first results', async () => {
    let answerRecents
    bandSpaceSearchApi.search = (_bandSpaceId, term) =>
      term === ''
        ? new Promise((resolve) => {
            answerRecents = () => resolve([hit('song', 'late')])
          })
        : Promise.resolve([hit('task', term)])

    const state = search()
    state.reset()
    state.query.value = 'mix'
    await wait(PAST_DEBOUNCE_MS)
    answerRecents()
    await wait(10)

    assert.deepEqual(
      state.flatResults.value.map((result) => result.id),
      ['task-mix']
    )
    assert.deepEqual(state.recents.value, [])
  })
})
