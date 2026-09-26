import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  ANNOUNCE_FILTER_ALL,
  announceHeadline,
  announceKindLabel,
  announceStyleTags,
  announceTypeForFilter,
  frequentSearchLink,
  LOOKING_FOR_BAND,
  LOOKING_FOR_MUSICIAN,
  musicianSearchRoute
} from './homeSearch.js'

const brussels = { name: 'Bruxelles', context: 'Belgique', latitude: 50.85, longitude: 4.35 }

describe('musicianSearchRoute', () => {
  it('looks for musicians among the announces musicians posted', () => {
    assert.deepEqual(musicianSearchRoute({ lookingFor: LOOKING_FOR_MUSICIAN }), {
      name: 'app_search_musician',
      query: { type: '2' }
    })
  })

  it('looks for bands among the announces bands posted, with instrument and city', () => {
    assert.deepEqual(
      musicianSearchRoute({
        lookingFor: LOOKING_FOR_BAND,
        instrument: { id: 'drums-id' },
        city: brussels
      }),
      {
        name: 'app_search_musician',
        query: {
          type: '1',
          instrument: 'drums-id',
          lat: '50.85',
          lng: '4.35',
          location: 'Bruxelles'
        }
      }
    )
  })

  it('drops a city typed but never picked, which has no coordinates', () => {
    assert.deepEqual(
      musicianSearchRoute({ lookingFor: LOOKING_FOR_MUSICIAN, city: 'Brux' }).query,
      {
        type: '2'
      }
    )
  })

  it('keeps a city on the equator or the meridian', () => {
    const query = musicianSearchRoute({
      lookingFor: LOOKING_FOR_MUSICIAN,
      city: { name: 'Nulle part', latitude: 0, longitude: 0 }
    }).query
    assert.equal(query.lat, '0')
    assert.equal(query.lng, '0')
  })
})

describe('announce card text', () => {
  const byBand = { type: 1, instrument: { musician_name: 'Batteur' }, styles: [] }
  const byMusician = { type: 2, instrument: { musician_name: 'Guitariste' }, styles: [] }

  it('names what the announce is looking for', () => {
    assert.equal(announceHeadline(byBand), 'Groupe cherche un batteur')
    assert.equal(announceHeadline(byMusician), 'Guitariste cherche un groupe')
  })

  it('says who posted it', () => {
    assert.equal(announceKindLabel(byBand), 'Groupe')
    assert.equal(announceKindLabel(byMusician), 'Musicien·ne')
  })

  it('shows the first styles and counts the rest', () => {
    const styles = ['Rock', 'Pop', 'Jazz', 'Funk', 'Métal'].map((name) => ({ name }))
    assert.deepEqual(announceStyleTags({ styles }), { tags: ['Rock', 'Pop', 'Jazz'], remaining: 2 })
    assert.deepEqual(announceStyleTags({ styles: styles.slice(0, 2) }), {
      tags: ['Rock', 'Pop'],
      remaining: 0
    })
  })
})

describe('announceTypeForFilter', () => {
  it('asks for every announce under « Toutes », and for the type otherwise', () => {
    assert.equal(announceTypeForFilter(ANNOUNCE_FILTER_ALL), null)
    assert.equal(announceTypeForFilter(1), 1)
    assert.equal(announceTypeForFilter(2), 2)
  })
})

describe('frequentSearchLink', () => {
  const search = {
    instrument_id: 'drums-id',
    instrument_name: 'Batteur',
    location_name: 'Bruxelles',
    latitude: 50.84,
    longitude: 4.35
  }

  it('names a search for musicians by instrument and city, and runs it again', () => {
    assert.deepEqual(frequentSearchLink({ ...search, type: 2 }), {
      label: 'Batteur à Bruxelles',
      route: {
        name: 'app_search_musician',
        query: {
          type: '2',
          instrument: 'drums-id',
          lat: '50.84',
          lng: '4.35',
          location: 'Bruxelles'
        }
      }
    })
  })

  it('says a band is looking when the search was for the bands', () => {
    const link = frequentSearchLink({ ...search, type: 1 })
    assert.equal(link.label, 'Groupe cherchant un batteur à Bruxelles')
    assert.equal(link.route.query.type, '1')
  })
})
