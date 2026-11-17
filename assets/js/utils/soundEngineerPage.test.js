import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  headlineOffer,
  MAX_GEAR,
  offersPayload,
  orderedSpecialities,
  parseGear,
  placesForm,
  placesPayload,
  placesSummary
} from './soundEngineerPage.js'

describe('headlineOffer', () => {
  it('leads with the cheapest price per track, even when a day costs less', () => {
    const offers = [
      { id: 'day', unit: 'day', price: 6000 },
      { id: 'mix', unit: 'track', price: 8000 },
      { id: 'master', unit: 'track', price: 2500 }
    ]
    assert.equal(headlineOffer(offers).id, 'master')
  })

  it('falls back on the cheapest of any unit without a price per track', () => {
    const offers = [
      { id: 'ep', unit: 'flat', price: 45000 },
      { id: 'hour', unit: 'hour', price: 4000 }
    ]
    assert.equal(headlineOffer(offers).id, 'hour')
  })

  it('has nothing to say without offers', () => {
    assert.equal(headlineOffer([]), null)
    assert.equal(headlineOffer(undefined), null)
  })
})

describe('placesSummary', () => {
  it('names the studio city, the travel radius and remote work', () => {
    assert.deepEqual(
      placesSummary([
        { type: 'remote', city: null, radius: null },
        { type: 'at_client_place', city: 'Bruxelles', radius: 30 },
        { type: 'at_my_place', city: 'Forest', radius: null }
      ]),
      {
        studioCity: 'Forest',
        city: 'Forest',
        travelRadius: 30,
        travels: true,
        remote: true
      }
    )
  })

  it('takes any city when there is no studio', () => {
    assert.deepEqual(placesSummary([{ type: 'at_client_place', city: 'Liège', radius: null }]), {
      studioCity: null,
      city: 'Liège',
      travelRadius: null,
      travels: true,
      remote: false
    })
  })

  it('says nothing for no places', () => {
    assert.deepEqual(placesSummary(null), {
      studioCity: null,
      city: null,
      travelRadius: null,
      travels: false,
      remote: false
    })
  })
})

describe('orderedSpecialities', () => {
  it('keeps the kind order and drops what it does not know', () => {
    assert.deepEqual(orderedSpecialities(['editing', 'nope', 'mixing']), ['mixing', 'editing'])
  })
})

describe('parseGear', () => {
  it('splits on commas and lines, trims, and drops empties and repeats', () => {
    assert.deepEqual(parseGear(' Reaper, UAD Apollo x8\nNeumann U87,, Reaper \n'), [
      'Reaper',
      'UAD Apollo x8',
      'Neumann U87'
    ])
  })

  it('keeps at most the limit the API allows', () => {
    const text = Array.from({ length: MAX_GEAR + 5 }, (_, i) => `Item ${i}`).join(',')
    assert.equal(parseGear(text).length, MAX_GEAR)
  })
})

describe('placesForm and placesPayload', () => {
  const stored = [
    {
      type: 'at_my_place',
      city: 'Bruxelles',
      country: 'Belgique',
      latitude: 50.85,
      longitude: 4.35,
      address: 'Rue de Forest 1',
      radius: null
    },
    {
      type: 'remote',
      city: null,
      country: null,
      latitude: null,
      longitude: null,
      address: null,
      radius: null
    }
  ]

  it('opens the stored places as the three ticks of the form', () => {
    const form = placesForm(stored)
    assert.equal(form.studio.enabled, true)
    assert.equal(form.studio.address, 'Rue de Forest 1')
    assert.equal(form.client.enabled, false)
    assert.equal(form.remote, true)
  })

  it('saves back what it opened', () => {
    assert.deepEqual(placesPayload(placesForm(stored)), [
      {
        type: 'at_my_place',
        city: 'Bruxelles',
        country: 'Belgique',
        latitude: 50.85,
        longitude: 4.35,
        address: 'Rue de Forest 1'
      },
      { type: 'remote' }
    ])
  })

  it('sends a travel radius only for the travel to the client, and nothing unticked', () => {
    const form = placesForm([])
    form.client = {
      ...form.client,
      enabled: true,
      city: 'Liège',
      latitude: 50.63,
      longitude: 5.57,
      radius: 30
    }
    assert.deepEqual(placesPayload(form), [
      {
        type: 'at_client_place',
        city: 'Liège',
        country: null,
        latitude: 50.63,
        longitude: 5.57,
        radius: 30
      }
    ])
  })
})

describe('offersPayload', () => {
  it('sends euros as cents, trims, and drops a row without a title', () => {
    assert.deepEqual(
      offersPayload([
        { unit: 'track', title: " Mixage d'un titre ", description: ' ', price: 80 },
        { unit: 'flat', title: '', description: 'oublié', price: 10 },
        { unit: 'flat', title: 'EP', description: 'Mixage et master', price: 449.99 },
        { unit: 'hour', title: 'Une heure', description: '', price: null }
      ]),
      [
        { unit: 'track', title: "Mixage d'un titre", description: null, price: 8000 },
        { unit: 'flat', title: 'EP', description: 'Mixage et master', price: 44999 },
        { unit: 'hour', title: 'Une heure', description: null, price: null }
      ]
    )
  })
})
