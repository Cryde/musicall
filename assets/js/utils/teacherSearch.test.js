import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  effectiveOrder,
  emptyTeacherFilters,
  formatDistance,
  instrumentOf,
  instrumentsOf,
  teacherFiltersFromUrlQuery,
  teacherSearchParams,
  teacherSearchUrlQuery
} from './teacherSearch.js'

const BASS = { id: 'bass-id', slug: 'basse', name: 'Basse' }
const GUITAR = { id: 'guitar-id', slug: 'guitare', name: 'Guitare' }
const BRUSSELS = { name: 'Bruxelles', latitude: 50.8503, longitude: 4.3517 }

const filters = (overrides) => ({ ...emptyTeacherFilters(), ...overrides })

describe('effectiveOrder', () => {
  it('is the nearest first once there is a city, by default', () => {
    assert.equal(effectiveOrder(null, true), 'distance')
  })

  it('is none (the newest) without a city, even when the nearest was asked', () => {
    assert.equal(effectiveOrder(null, false), null)
    assert.equal(effectiveOrder('distance', false), null)
  })

  it('keeps an order that needs no city', () => {
    assert.equal(effectiveOrder('price', false), 'price')
    assert.equal(effectiveOrder('free_trial', true), 'free_trial')
  })
})

describe('teacherSearchParams', () => {
  it('asks nothing for no filter', () => {
    assert.deepEqual(teacherSearchParams(emptyTeacherFilters()), {})
  })

  it('asks every filter, the instrument by its id and the city by its point', () => {
    assert.deepEqual(
      teacherSearchParams(
        filters({
          instrument: BASS,
          city: BRUSSELS,
          radius: 25,
          level: 'beginner',
          ageGroup: 'adults',
          online: true,
          freeTrial: true,
          order: 'price'
        }),
        2
      ),
      {
        instrument: 'bass-id',
        lat: 50.8503,
        lng: 4.3517,
        radius: 25,
        online: '1',
        level: 'beginner',
        age_group: 'adults',
        free_trial: '1',
        order: 'price',
        page: 2
      }
    )
  })

  it('leaves « En ligne aussi » out without a city, which it only widens', () => {
    assert.deepEqual(teacherSearchParams(filters({ online: true })), {})
  })

  it('leaves out the nearest first, the default once there is a city', () => {
    assert.deepEqual(teacherSearchParams(filters({ city: BRUSSELS, order: 'distance' })), {
      lat: 50.8503,
      lng: 4.3517,
      radius: 10
    })
  })
})

describe('teacherSearchUrlQuery and teacherFiltersFromUrlQuery', () => {
  it('writes the instrument by its slug and the city by its name and point', () => {
    assert.deepEqual(
      teacherSearchUrlQuery(
        filters({ instrument: BASS, city: BRUSSELS, level: 'beginner', online: true })
      ),
      {
        instrument: 'basse',
        location: 'Bruxelles',
        lat: '50.8503',
        lng: '4.3517',
        radius: '10',
        online: '1',
        level: 'beginner'
      }
    )
  })

  it('reads back what it wrote', () => {
    const written = filters({
      instrument: GUITAR,
      city: BRUSSELS,
      radius: 50,
      level: 'advanced',
      ageGroup: 'children',
      online: true,
      freeTrial: true,
      order: 'free_trial'
    })

    assert.deepEqual(
      teacherFiltersFromUrlQuery(teacherSearchUrlQuery(written), [BASS, GUITAR]),
      written
    )
  })

  it('leaves out what the search does not know', () => {
    assert.deepEqual(
      teacherFiltersFromUrlQuery(
        {
          instrument: 'kazoo',
          location: 'Bruxelles',
          lat: 'north',
          lng: '4.35',
          level: 'expert',
          age_group: ['adults'],
          order: 'cheapest'
        },
        [BASS]
      ),
      filters({ ageGroup: 'adults' })
    )
  })

  it('takes the default radius for one the filter does not offer', () => {
    const read = teacherFiltersFromUrlQuery(
      { location: 'Bruxelles', lat: '50.85', lng: '4.35', radius: '7' },
      []
    )

    assert.equal(read.radius, 10)
  })
})

describe('instrumentOf and instrumentsOf', () => {
  it('elides « de » before a vowel in French', () => {
    assert.equal(instrumentOf('Basse', 'fr-FR'), 'de basse')
    assert.equal(instrumentOf('Orgue', 'fr-FR'), "d'orgue")
    assert.equal(instrumentOf('Accordéon', 'fr-FR'), "d'accordéon")
  })

  it('leaves an h alone', () => {
    assert.equal(instrumentOf('Harpe', 'fr-FR'), 'de harpe')
  })

  it('names the instrument alone in English', () => {
    assert.equal(instrumentOf('Bass', 'en-GB'), 'bass')
  })

  it('lists several the way each language does', () => {
    assert.equal(instrumentsOf(['Basse', 'Guitare'], 'fr-FR'), 'de basse et de guitare')
    assert.equal(
      instrumentsOf(['Basse', 'Orgue', 'Piano'], 'fr-FR'),
      "de basse, d'orgue et de piano"
    )
    assert.equal(instrumentsOf(['Bass', 'Guitar'], 'en-GB'), 'bass and guitar')
  })
})

describe('formatDistance', () => {
  // The space before « km » is whichever one the ICU in use sets.
  it('writes one decimal, in the locale', () => {
    assert.match(formatDistance(1.1, 'fr-FR'), /^1,1\skm$/)
    assert.match(formatDistance(24, 'en-GB'), /^24\.0\skm$/)
  })
})
