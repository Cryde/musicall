import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  emptySoundEngineerFilters,
  soundEngineerFiltersFromUrlQuery,
  soundEngineerSearchParams,
  soundEngineerSearchUrlQuery
} from './soundEngineerSearch.js'

const ROCK = { id: 'style-rock', slug: 'rock', name: 'Rock' }
const BRUSSELS = { name: 'Bruxelles', latitude: 50.85, longitude: 4.35 }

describe('soundEngineerSearchParams', () => {
  it('asks nothing for no filter', () => {
    assert.deepEqual(soundEngineerSearchParams(emptySoundEngineerFilters()), {})
  })

  it('asks the speciality, the style by id, the city with its radius and remote work, and the page', () => {
    const filters = { speciality: 'mixing', style: ROCK, city: BRUSSELS, radius: 25, online: true }
    assert.deepEqual(soundEngineerSearchParams(filters, 2), {
      speciality: 'mixing',
      style: 'style-rock',
      lat: 50.85,
      lng: 4.35,
      radius: 25,
      online: '1',
      page: 2
    })
  })

  it('asks remote work only around a city', () => {
    assert.deepEqual(
      soundEngineerSearchParams({ ...emptySoundEngineerFilters(), online: true }),
      {}
    )
  })
})

describe('the address of a search', () => {
  it('keeps the style by slug and names the city, and reads back the same filters', () => {
    const filters = {
      speciality: 'mastering',
      style: ROCK,
      city: BRUSSELS,
      radius: 50,
      online: false
    }
    const query = soundEngineerSearchUrlQuery(filters)
    assert.deepEqual(query, {
      speciality: 'mastering',
      style: 'rock',
      lat: '50.85',
      lng: '4.35',
      radius: '50',
      location: 'Bruxelles'
    })
    assert.deepEqual(soundEngineerFiltersFromUrlQuery(query, [ROCK]), filters)
  })

  it('leaves out what the search does not know', () => {
    assert.deepEqual(
      soundEngineerFiltersFromUrlQuery(
        { speciality: 'teaching', style: 'polka', location: 'Bruxelles', radius: '7' },
        [ROCK]
      ),
      emptySoundEngineerFilters()
    )
  })
})
