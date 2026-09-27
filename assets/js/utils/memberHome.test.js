import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  announceCriteriaFor,
  greetingFor,
  lastChangedSetlist,
  MEMBER_LAYOUT_BAND,
  MEMBER_LAYOUT_SEARCH,
  matchAsAnnounce,
  matchReason,
  memberLayoutFor,
  openTasks,
  pickBandSpace,
  searchResultAsAnnounce
} from './memberHome.js'

describe('greetingFor', () => {
  it('says good morning in the day and good evening from six', () => {
    assert.equal(greetingFor(new Date(2026, 8, 27, 9, 0)), 'Bonjour')
    assert.equal(greetingFor(new Date(2026, 8, 27, 17, 59)), 'Bonjour')
    assert.equal(greetingFor(new Date(2026, 8, 27, 18, 0)), 'Bonsoir')
    assert.equal(greetingFor(new Date(2026, 8, 27, 2, 0)), 'Bonsoir')
    assert.equal(greetingFor(new Date(2026, 8, 27, 5, 0)), 'Bonjour')
  })
})

describe('memberLayoutFor', () => {
  it('puts the band first for a member with a Band Space, the search first otherwise', () => {
    assert.equal(memberLayoutFor([{ id: 'a' }]), MEMBER_LAYOUT_BAND)
    assert.equal(memberLayoutFor([]), MEMBER_LAYOUT_SEARCH)
  })
})

describe('pickBandSpace', () => {
  const spaces = [{ id: 'a' }, { id: 'b' }]

  it('keeps the space last opened', () => {
    assert.equal(pickBandSpace(spaces, 'b').id, 'b')
  })

  it('falls back on the first when the last one is gone, and on nothing without a space', () => {
    assert.equal(pickBandSpace(spaces, 'left').id, 'a')
    assert.equal(pickBandSpace([], 'a'), null)
  })
})

describe('lastChangedSetlist', () => {
  it('takes the one changed last and counts its songs only', () => {
    const latest = lastChangedSetlist([
      {
        id: 'old',
        creation_datetime: '2026-09-01T10:00:00+00:00',
        update_datetime: null,
        items: []
      },
      {
        id: 'edited',
        creation_datetime: '2026-08-01T10:00:00+00:00',
        update_datetime: '2026-09-20T10:00:00+00:00',
        items: [{ type: 'song' }, { type: 'break' }, { type: 'song' }]
      }
    ])
    assert.equal(latest.setlist.id, 'edited')
    assert.equal(latest.changedAt, '2026-09-20T10:00:00+00:00')
    assert.equal(latest.songCount, 2)
  })

  it('is nothing without a setlist', () => {
    assert.equal(lastChangedSetlist([]), null)
  })
})

describe('openTasks', () => {
  it('leaves the done ones out and puts the soonest due first, the undated last', () => {
    const tasks = [
      { title: 'Payer la salle', status: 'done', due_date: '2026-09-01' },
      { title: 'Réserver le local', status: 'todo', due_date: null },
      { title: 'Envoyer le tech rider', status: 'in_progress', due_date: '2026-10-02' },
      { title: 'Relancer la salle', status: 'todo', due_date: '2026-09-30' }
    ]
    assert.deepEqual(
      openTasks(tasks, 3).map((task) => task.title),
      ['Relancer la salle', 'Envoyer le tech rider', 'Réserver le local']
    )
    assert.equal(openTasks(tasks, 1).length, 1)
  })
})

describe('announces for a member', () => {
  it('searches bands looking for the first instrument of the profile', () => {
    assert.deepEqual(
      announceCriteriaFor({
        instruments: [
          { instrument_id: 'drums', instrument_name: 'Batterie' },
          { instrument_id: 'bass' }
        ]
      }),
      { type: 1, instrumentId: 'drums', instrumentName: 'Batterie' }
    )
  })

  it('has nothing to search without a profile or an instrument', () => {
    assert.equal(announceCriteriaFor(null), null)
    assert.equal(announceCriteriaFor({ instruments: [] }), null)
  })

  it('reads a search result the way the announce card does', () => {
    const user = { id: 'u', username: 'lea' }
    assert.deepEqual(
      searchResultAsAnnounce({
        id: 'x',
        type: 1,
        instrument: { name: 'Batteur' },
        styles: [{ name: 'Rock' }],
        location_name: 'Mons',
        user
      }),
      {
        id: 'x',
        type: 1,
        instrument: { musician_name: 'Batteur' },
        styles: [{ name: 'Rock' }],
        location_name: 'Mons',
        author: user,
        creation_datetime: null
      }
    )
  })
})

describe('matchReason', () => {
  const answered = { id: 'own', type: 2, instrument_name: 'Batteur', location_name: 'Bruxelles' }

  it('names the announce it answers, how far and the styles in common', () => {
    assert.equal(
      matchReason({ announce: { distance: 5.53 }, answered, shared_styles: ['Rock', 'Pop'] }),
      'Répond à votre annonce « Batteur cherche un groupe » · à 6 km · 2 styles en commun'
    )
  })

  it('says one style, a kilometre for nearer than that, and nothing for no style in common', () => {
    assert.equal(
      matchReason({ announce: { distance: 0.2 }, answered, shared_styles: ['Rock'] }),
      'Répond à votre annonce « Batteur cherche un groupe » · à 1 km · 1 style en commun'
    )
    assert.equal(
      matchReason({
        announce: { distance: 12.4 },
        answered: { ...answered, type: 1 },
        shared_styles: []
      }),
      'Répond à votre annonce « Groupe cherche un batteur » · à 12 km'
    )
  })
})

describe('matchAsAnnounce', () => {
  it('reads as a search result, with the reason it is shown', () => {
    const user = { id: 'u', username: 'legroupe' }
    const announce = matchAsAnnounce({
      announce: {
        id: 'x',
        type: 1,
        instrument: { name: 'Batteur' },
        styles: [],
        location_name: 'Ixelles',
        user,
        distance: 2.16
      },
      answered: { id: 'own', type: 2, instrument_name: 'Batteur', location_name: 'Bruxelles' },
      shared_styles: []
    })
    assert.equal(announce.id, 'x')
    assert.equal(announce.author, user)
    assert.equal(announce.reason, 'Répond à votre annonce « Batteur cherche un groupe » · à 2 km')
  })
})
