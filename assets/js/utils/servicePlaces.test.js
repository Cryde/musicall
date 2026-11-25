import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { placeLines, placesForm, placesPayload, placesSummary } from './servicePlaces.js'

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

  it('opens a service with no place yet with remote work ticked when its kind works remotely by default (#1413)', () => {
    assert.equal(placesForm([], { remoteByDefault: true }).remote, true)
    assert.deepEqual(placesPayload(placesForm(null, { remoteByDefault: true })), [
      { type: 'remote' }
    ])
    assert.equal(placesForm([]).remote, false)
  })

  it('keeps what is stored over the default, remote work left out included', () => {
    const studioOnly = stored.filter((place) => place.type !== 'remote')
    assert.equal(placesForm(studioOnly, { remoteByDefault: true }).remote, false)
    assert.equal(placesForm(stored, { remoteByDefault: true }).remote, true)
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

describe('placeLines', () => {
  const t = (key, params) => (params ? `${key}(${JSON.stringify(params)})` : key)
  const places = [
    { type: 'at_my_place', city: 'Forest', radius: null },
    { type: 'at_client_place', city: 'Bruxelles', radius: 30 },
    { type: 'at_client_place', city: 'Liège', radius: null },
    { type: 'remote', city: null, radius: null }
  ]

  it('names each place, the travel by its reach and remote work by what the kind says of it', () => {
    assert.deepEqual(
      placeLines(
        places,
        {
          placeKeys: { at_my_place: 'studio', at_client_place: 'client', remote: 'remote' },
          remoteDetailKey: 'remote_detail'
        },
        t
      ),
      [
        { icon: 'pi pi-building', label: 'studio', detail: 'Forest' },
        {
          icon: 'pi pi-car',
          label: 'client',
          detail: 'profile.service_page.travels_up_to({"radius":30})'
        },
        { icon: 'pi pi-car', label: 'client', detail: 'Liège' },
        { icon: 'pi pi-cloud-upload', label: 'remote', detail: 'remote_detail' }
      ]
    )
  })

  it('leaves out a place the kind does not name', () => {
    assert.deepEqual(
      placeLines(places, { placeKeys: { at_my_place: 'studio' } }, t).map((line) => line.label),
      ['studio']
    )
  })
})
