import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { announceDraftPayload, announceModalProps } from './announceDraft.js'
import { keepPendingAction, readPendingAction } from './pendingAction.js'

/**
 * « Nouvelle annonce » filled in by a visitor has to come back after sign-in as they left it, and a draft that no
 * longer fits has to reopen on the step that asks for it again rather than break the modal. Run with `npm test`.
 */

const DRUMS = { id: 'instrument-drums', musician_name: 'Batteur' }
const BASS = { id: 'instrument-bass', musician_name: 'Bassiste' }
const ROCK = { id: 'style-rock', name: 'Rock' }
const JAZZ = { id: 'style-jazz', name: 'Jazz' }
const FUNK = { id: 'style-funk', name: 'Funk' }
const LISTS = { instruments: [BASS, DRUMS], styles: [ROCK, JAZZ, FUNK] }
const LYON = {
  name: 'Lyon',
  context: 'Rhône, France',
  latitude: 45.758,
  longitude: 4.835,
  fullName: 'Lyon, Rhône, France'
}

const FORM = {
  type: 'musician',
  instrument: DRUMS,
  styles: [JAZZ, ROCK],
  location: LYON,
  note: 'Répétitions le jeudi soir',
  languages: ['fr', 'en']
}

function fakeStorage() {
  const values = {}

  return {
    getItem: (key) => (key in values ? values[key] : null),
    setItem: (key, value) => {
      values[key] = value
    },
    removeItem: (key) => {
      delete values[key]
    }
  }
}

describe('announceDraftPayload', () => {
  it('keeps the ids of the lists, the city and what was typed', () => {
    assert.deepEqual(announceDraftPayload(FORM), {
      type: 'musician',
      instrumentId: 'instrument-drums',
      styleIds: ['style-jazz', 'style-rock'],
      location: {
        name: 'Lyon',
        fullName: 'Lyon, Rhône, France',
        latitude: 45.758,
        longitude: 4.835
      },
      note: 'Répétitions le jeudi soir',
      languages: ['fr', 'en']
    })
  })

  it('is plain enough to be kept as a pending action', () => {
    const storage = fakeStorage()
    const kept = keepPendingAction(
      storage,
      {
        kind: 'announce.create',
        route: '/rechercher-un-musicien?type=2',
        payload: announceDraftPayload(FORM),
        stoppedOnSubmit: true
      },
      { isVisitor: true }
    )

    assert.equal(kept, true)
    assert.deepEqual(readPendingAction(storage).payload, announceDraftPayload(FORM))
  })

  it('stays plain with a city the geocoder sent without a name', () => {
    const storage = fakeStorage()
    const payload = announceDraftPayload({
      ...FORM,
      location: { latitude: 1, longitude: 2 },
      note: undefined
    })
    const kept = keepPendingAction(
      storage,
      {
        kind: 'announce.create',
        route: '/rechercher-un-musicien?type=2',
        payload,
        stoppedOnSubmit: true
      },
      { isVisitor: true }
    )

    assert.equal(kept, true)
  })
})

describe('announceModalProps', () => {
  it('gives the modal back what the visitor filled in', () => {
    assert.deepEqual(announceModalProps(announceDraftPayload(FORM), LISTS), {
      initialType: 'musician',
      initialInstrument: DRUMS,
      initialStyles: [JAZZ, ROCK],
      initialLocation: {
        name: 'Lyon',
        fullName: 'Lyon, Rhône, France',
        latitude: 45.758,
        longitude: 4.835
      },
      initialNote: 'Répétitions le jeudi soir',
      initialLanguages: ['fr', 'en']
    })
  })

  it('leaves out an instrument and a style that are no longer in the lists', () => {
    const props = announceModalProps(
      announceDraftPayload({ ...FORM, instrument: { id: 'gone' }, styles: [{ id: 'gone' }, FUNK] }),
      LISTS
    )

    assert.equal(props.initialInstrument, null)
    assert.deepEqual(props.initialStyles, [FUNK])
  })

  it('drops what a tampered payload cannot mean', () => {
    assert.deepEqual(
      announceModalProps(
        {
          type: 'teacher',
          instrumentId: 42,
          styleIds: 'style-rock',
          location: { name: 'Lyon', fullName: 'Lyon', latitude: 'north', longitude: 4.8 },
          note: ['<b>'],
          languages: ['fr', 'xx', 'fr']
        },
        LISTS
      ),
      {
        initialType: null,
        initialInstrument: null,
        initialStyles: [],
        initialLocation: null,
        initialNote: '',
        initialLanguages: ['fr']
      }
    )
  })

  it('opens an empty modal for a payload that is not an object', () => {
    assert.deepEqual(announceModalProps(null, LISTS), {
      initialType: null,
      initialInstrument: null,
      initialStyles: [],
      initialLocation: null,
      initialNote: '',
      initialLanguages: []
    })
  })
})
