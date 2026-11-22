import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  activeSearchFilters,
  distanceToSearchedCity,
  requestAnswersFromSearch,
  searchMatches,
  searchResultsPosition,
  shareableUrl,
  teacherPageLocation
} from './teacherResults.js'
import { emptyTeacherFilters } from './teacherSearch.js'

const BASS = { id: 'bass-id', slug: 'basse', name: 'Basse' }
const BRUSSELS = { name: 'Bruxelles', latitude: 50.8503, longitude: 4.3517 }

const filters = (overrides) => ({ ...emptyTeacherFilters(), ...overrides })
const teacher = (username, overrides = {}) => ({
  username,
  name: username,
  distance: null,
  online: false,
  ...overrides
})
const loaded = (usernames, { totalItems = usernames.length, hasMore = false } = {}) => ({
  teachers: usernames.map((username) => teacher(username)),
  totalItems,
  hasMore
})

describe('searchResultsPosition', () => {
  it('places the teacher among the loaded results, with the ones on either side', () => {
    const position = searchResultsPosition(
      loaded(['ana', 'ben', 'cleo'], { totalItems: 12, hasMore: true }),
      'ben'
    )

    assert.equal(position.position, 2)
    assert.equal(position.total, 12)
    assert.equal(position.current.username, 'ben')
    assert.equal(position.previous.username, 'ana')
    assert.equal(position.next.username, 'cleo')
  })

  it('has no previous teacher for the first result', () => {
    assert.equal(searchResultsPosition(loaded(['ana', 'ben']), 'ana').previous, null)
  })

  it('says whether more results follow the last loaded one', () => {
    const more = searchResultsPosition(
      loaded(['ana', 'ben'], { totalItems: 14, hasMore: true }),
      'ben'
    )
    const last = searchResultsPosition(loaded(['ana', 'ben']), 'ben')

    assert.equal(more.next, null)
    assert.equal(more.hasMore, true)
    assert.equal(last.next, null)
    assert.equal(last.hasMore, false)
  })

  it('is none for a teacher who is not in the results, or without results', () => {
    assert.equal(searchResultsPosition(loaded(['ana', 'ben']), 'zoe'), null)
    assert.equal(searchResultsPosition(loaded([]), 'ana'), null)
  })
})

describe('teacherPageLocation', () => {
  it('opens the lessons page on the searched instrument, from the search', () => {
    assert.deepEqual(teacherPageLocation('julien', 'basse'), {
      name: 'app_user_teacher_profile',
      params: { username: 'julien' },
      query: { instrument: 'basse', from: 'search' }
    })
  })

  it('names no instrument when none was searched', () => {
    assert.deepEqual(teacherPageLocation('julien', null).query, { from: 'search' })
  })
})

describe('activeSearchFilters', () => {
  it('lists every active filter, the instrument first', () => {
    const active = activeSearchFilters(
      filters({
        instrument: BASS,
        city: BRUSSELS,
        radius: 25,
        level: 'beginner',
        ageGroup: 'adults',
        online: true,
        freeTrial: true
      })
    )

    assert.deepEqual(active, [
      { key: 'instrument', name: 'Basse' },
      { key: 'city', city: 'Bruxelles', radius: 25 },
      { key: 'level', level: 'beginner' },
      { key: 'age_group', ageGroup: 'adults' },
      { key: 'online' },
      { key: 'free_trial' }
    ])
  })

  it('leaves out « online » without a city, which it only widens', () => {
    assert.deepEqual(activeSearchFilters(filters({ online: true })), [])
  })

  it('has none without filters', () => {
    assert.deepEqual(activeSearchFilters(filters({})), [])
    assert.deepEqual(activeSearchFilters(null), [])
  })
})

describe('distanceToSearchedCity', () => {
  it('is the distance to the searched city', () => {
    assert.deepEqual(
      distanceToSearchedCity(filters({ city: BRUSSELS }), teacher('julien', { distance: 2.4 })),
      { distance: 2.4, city: 'Bruxelles' }
    )
  })

  it('is none without a city, or for a teacher found online only', () => {
    assert.equal(distanceToSearchedCity(filters({}), teacher('julien', { distance: 2.4 })), null)
    assert.equal(
      distanceToSearchedCity(filters({ city: BRUSSELS }), teacher('julien', { online: true })),
      null
    )
    assert.equal(distanceToSearchedCity(null, null), null)
  })
})

describe('searchMatches', () => {
  it('names the filters, then the distance to the searched city', () => {
    const matches = searchMatches(
      filters({ instrument: BASS, city: BRUSSELS, level: 'beginner', ageGroup: 'adults' }),
      teacher('julien', { distance: 2.4 })
    )

    assert.deepEqual(matches, [
      { key: 'instrument', name: 'Basse' },
      { key: 'level', level: 'beginner' },
      { key: 'age_group', ageGroup: 'adults' },
      { key: 'distance', distance: 2.4, city: 'Bruxelles' }
    ])
  })

  it('matches the city by teaching online for a teacher found online only', () => {
    const matches = searchMatches(
      filters({ city: BRUSSELS, online: true, freeTrial: true }),
      teacher('julien', { online: true })
    )

    assert.deepEqual(matches, [{ key: 'free_trial' }, { key: 'online' }])
  })

  it('leaves « online » out once the distance says where', () => {
    const matches = searchMatches(
      filters({ city: BRUSSELS, online: true }),
      teacher('julien', { distance: 3, online: true })
    )

    assert.deepEqual(matches, [{ key: 'distance', distance: 3, city: 'Bruxelles' }])
  })

  it('has nothing to say without filters or without the result', () => {
    assert.deepEqual(searchMatches(filters({}), teacher('julien', { distance: 3 })), [])
    assert.deepEqual(searchMatches(null, teacher('julien')), [])
    assert.deepEqual(searchMatches(filters({ instrument: BASS }), null), [])
  })
})

describe('requestAnswersFromSearch', () => {
  it('takes the searched instrument and level', () => {
    assert.deepEqual(
      requestAnswersFromSearch(
        filters({ instrument: BASS, level: 'beginner', ageGroup: 'adults' })
      ),
      { instrument: 'bass-id', level: 'beginner' }
    )
  })

  it('takes nothing the search did not ask', () => {
    assert.deepEqual(requestAnswersFromSearch(filters({})), {})
    assert.deepEqual(requestAnswersFromSearch(null), {})
  })
})

describe('shareableUrl', () => {
  it('drops how the visitor arrived and keeps the instrument, which only reorders', () => {
    assert.equal(
      shareableUrl('/u/julien/teacher?instrument=basse&from=search', 'https://musicall.com'),
      'https://musicall.com/u/julien/teacher?instrument=basse'
    )
  })

  it('leaves a bare address bare', () => {
    assert.equal(
      shareableUrl('/en/u/julien/teacher?from=profile', 'https://musicall.com'),
      'https://musicall.com/en/u/julien/teacher'
    )
  })
})
