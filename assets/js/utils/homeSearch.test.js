import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  ANNOUNCE_FILTER_ALL,
  announceAuthorRoute,
  announceHeadline,
  announceKindLabel,
  announceRowMeta,
  announceStyleTags,
  announceTypeForFilter,
  canContactAnnounceAuthor,
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

  it('shows the first styles and keeps the others for the « +N »', () => {
    const styles = ['Rock', 'Pop', 'Jazz', 'Funk', 'Métal'].map((name) => ({ name }))
    assert.deepEqual(announceStyleTags({ styles }), {
      tags: ['Rock', 'Pop', 'Jazz'],
      hidden: ['Funk', 'Métal']
    })
    assert.deepEqual(announceStyleTags({ styles: styles.slice(0, 2) }), {
      tags: ['Rock', 'Pop'],
      hidden: []
    })
  })
})

describe('announce author', () => {
  const author = {
    id: 'author-id',
    username: 'aoife',
    has_musician_profile: true,
    deletion_datetime: null
  }

  it('leads to the profile, with the way back to the home, musician profile or not', () => {
    for (const hasMusicianProfile of [true, false]) {
      assert.deepEqual(
        announceAuthorRoute({ author: { ...author, has_musician_profile: hasMusicianProfile } }),
        {
          name: 'app_user_public_profile',
          params: { username: 'aoife' },
          query: { from: 'home' }
        }
      )
    }
  })

  it('can be contacted by a visitor or another member, never by the author', () => {
    assert.equal(canContactAnnounceAuthor({ author }, undefined), true)
    assert.equal(canContactAnnounceAuthor({ author }, 'someone-else'), true)
    assert.equal(canContactAnnounceAuthor({ author }, 'author-id'), false)
  })

  it('cannot be contacted once the account is closed', () => {
    const closed = { ...author, deletion_datetime: '2026-10-01T09:00:00+00:00' }
    assert.equal(canContactAnnounceAuthor({ author: closed }, 'someone-else'), false)
  })
})

describe('announceRowMeta', () => {
  it('joins who, where and when with the locale separator', () => {
    assert.equal(
      announceRowMeta(['Aoife Ryan', 'Dublin', '29 octobre']),
      'Aoife Ryan · Dublin · 29 octobre'
    )
  })

  it('leaves out what the announce does not say', () => {
    assert.equal(announceRowMeta(['Aoife Ryan', null, '29 octobre']), 'Aoife Ryan · 29 octobre')
    assert.equal(announceRowMeta(['Aoife Ryan', '', null]), 'Aoife Ryan')
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
