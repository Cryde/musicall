import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  goBackToResults,
  goToNextTeacher,
  goToPreviousTeacher,
  loadNextResultsPage
} from './teacherResultsNavigation.js'
import { emptyTeacherFilters } from './teacherSearch.js'

const BASS = { id: 'bass-id', slug: 'basse', name: 'Basse' }
const SEARCHED = { ...emptyTeacherFilters(), instrument: BASS }

const teacher = (username) => ({ username, name: username })

/** The search store as the navigation reads it; `search` answers with the teachers of `pages[page - 1]`. */
function fakeStore({ pages, loaded = 1, filters = SEARCHED, answer = null }) {
  return {
    teachers: pages.slice(0, loaded).flat().map(teacher),
    totalItems: pages.flat().length,
    currentPage: loaded,
    filters,
    searches: [],
    get hasMore() {
      return this.teachers.length < this.totalItems
    },
    async search(searchFilters, page) {
      this.searches.push({ filters: searchFilters, page })
      await (answer ?? Promise.resolve())
      this.teachers = [...this.teachers, ...pages[page - 1].map(teacher)]
      this.currentPage = page
      return true
    }
  }
}

function fakeRouter(routeNames = {}) {
  return {
    replaced: [],
    pushed: [],
    backs: 0,
    replace(location) {
      this.replaced.push(location)
    },
    push(location) {
      this.pushed.push(location)
    },
    back() {
      this.backs++
    },
    resolve: (path) => ({ name: routeNames[path] ?? 'app_home' })
  }
}

const on = (username) => ({ params: { username } })
const teacherPage = (username) => ({
  name: 'app_user_teacher_profile',
  params: { username },
  query: { instrument: 'basse', from: 'search' }
})

describe('loadNextResultsPage', () => {
  it('searches the page after the last one loaded, with the filters the list was searched with', async () => {
    const store = fakeStore({ pages: [['ana'], ['ben'], ['cleo']], loaded: 2 })

    await loadNextResultsPage(store)

    assert.deepEqual(store.searches, [{ filters: SEARCHED, page: 3 }])
  })

  it('searches nothing before a first search', async () => {
    const store = fakeStore({ pages: [['ana']], filters: null })

    assert.equal(await loadNextResultsPage(store), false)
    assert.deepEqual(store.searches, [])
  })
})

describe('goToPreviousTeacher', () => {
  it('opens the teacher before, in place of this one', () => {
    const router = fakeRouter()

    goToPreviousTeacher({ store: fakeStore({ pages: [['ana', 'ben']] }), router, route: on('ben') })

    assert.deepEqual(router.replaced, [teacherPage('ana')])
  })

  it('stays on the first result', () => {
    const router = fakeRouter()

    goToPreviousTeacher({ store: fakeStore({ pages: [['ana', 'ben']] }), router, route: on('ana') })

    assert.deepEqual(router.replaced, [])
  })
})

describe('goToNextTeacher', () => {
  it('opens the next loaded teacher without searching', async () => {
    const store = fakeStore({ pages: [['ana', 'ben'], ['cleo']] })
    const router = fakeRouter()

    await goToNextTeacher({ store, router, route: on('ana'), isLoading: { value: false } })

    assert.deepEqual(store.searches, [])
    assert.deepEqual(router.replaced, [teacherPage('ben')])
  })

  it('loads the next page at the end of the loaded one, then opens its first teacher', async () => {
    const store = fakeStore({
      pages: [
        ['ana', 'ben'],
        ['cleo', 'dan']
      ]
    })
    const router = fakeRouter()

    await goToNextTeacher({ store, router, route: on('ben'), isLoading: { value: false } })

    assert.deepEqual(store.searches, [{ filters: SEARCHED, page: 2 }])
    assert.deepEqual(router.replaced, [teacherPage('cleo')])
  })

  it('stops at the last result', async () => {
    const store = fakeStore({ pages: [['ana', 'ben'], ['cleo']], loaded: 2 })
    const router = fakeRouter()

    await goToNextTeacher({ store, router, route: on('cleo'), isLoading: { value: false } })

    assert.deepEqual(store.searches, [])
    assert.deepEqual(router.replaced, [])
  })

  it('ignores a second press while the next page loads', async () => {
    let answerArrives
    const store = fakeStore({
      pages: [['ana'], ['ben']],
      answer: new Promise((resolve) => {
        answerArrives = resolve
      })
    })
    const router = fakeRouter()
    const isLoading = { value: false }

    const first = goToNextTeacher({ store, router, route: on('ana'), isLoading })
    assert.equal(isLoading.value, true)
    const second = goToNextTeacher({ store, router, route: on('ana'), isLoading })
    answerArrives()
    await Promise.all([first, second])

    assert.equal(store.searches.length, 1)
    assert.deepEqual(router.replaced, [teacherPage('ben')])
    assert.equal(isLoading.value, false)
  })

  it('stays on the teacher when the next page fails, ready for another press', async () => {
    const store = fakeStore({ pages: [['ana'], ['ben']] })
    store.search = async () => {
      throw new Error('offline')
    }
    const router = fakeRouter()
    const isLoading = { value: false }

    await goToNextTeacher({ store, router, route: on('ana'), isLoading })

    assert.deepEqual(router.replaced, [])
    assert.equal(isLoading.value, false)
  })
})

describe('goBackToResults', () => {
  const SEARCH_PATH = '/rechercher-un-professeur?instrument=basse'

  it('steps back when the page before is the teacher search', () => {
    const router = fakeRouter({ [SEARCH_PATH]: 'app_search_teacher' })

    goBackToResults({
      store: fakeStore({ pages: [['ana']] }),
      router,
      historyState: { back: SEARCH_PATH }
    })

    assert.equal(router.backs, 1)
    assert.deepEqual(router.pushed, [])
  })

  it('opens the search with its filters when the page before is another one, or there is none', () => {
    for (const historyState of [{ back: '/u/julien' }, { back: null }, null]) {
      const router = fakeRouter({ [SEARCH_PATH]: 'app_search_teacher' })

      goBackToResults({ store: fakeStore({ pages: [['ana']] }), router, historyState })

      assert.equal(router.backs, 0)
      assert.deepEqual(router.pushed, [
        { name: 'app_search_teacher', query: { instrument: 'basse' } }
      ])
    }
  })
})
