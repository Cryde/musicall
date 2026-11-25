import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { SPECIALITIES } from './soundEngineerPage.js'
import {
  emptySpecialityFilters,
  specialityFiltersFromUrlQuery,
  specialitySearchParams,
  specialitySearchUrlQuery
} from './specialitySearch.js'

const ROCK = { id: 'style-rock', slug: 'rock', name: 'Rock' }
const BRUSSELS = { name: 'Bruxelles', latitude: 50.85, longitude: 4.35 }

describe('specialitySearchParams', () => {
  it('asks nothing for no filter', () => {
    assert.deepEqual(specialitySearchParams(emptySpecialityFilters()), {})
  })

  it('asks the speciality, the style by id, the city with its radius and remote work, and the page', () => {
    const filters = { speciality: 'mixing', style: ROCK, city: BRUSSELS, radius: 25, online: true }
    assert.deepEqual(specialitySearchParams(filters, 2), {
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
    assert.deepEqual(specialitySearchParams({ ...emptySpecialityFilters(), online: true }), {})
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
    const query = specialitySearchUrlQuery(filters)
    assert.deepEqual(query, {
      speciality: 'mastering',
      style: 'rock',
      lat: '50.85',
      lng: '4.35',
      radius: '50',
      location: 'Bruxelles'
    })
    assert.deepEqual(specialityFiltersFromUrlQuery(query, [ROCK], SPECIALITIES), filters)
  })

  it('leaves out what the search does not know', () => {
    assert.deepEqual(
      specialityFiltersFromUrlQuery(
        { speciality: 'teaching', style: 'polka', location: 'Bruxelles', radius: '7' },
        [ROCK],
        SPECIALITIES
      ),
      emptySpecialityFilters()
    )
  })
})

describe('the specialities of another kind', () => {
  it('reads a speciality of the kind searched, never one of another kind', () => {
    const photographer = ['concert', 'promo', 'cover', 'studio', 'backstage_tour']
    assert.equal(
      specialityFiltersFromUrlQuery({ speciality: 'cover' }, [], photographer).speciality,
      'cover'
    )
    assert.equal(
      specialityFiltersFromUrlQuery({ speciality: 'mixing' }, [], photographer).speciality,
      null
    )
  })
})
