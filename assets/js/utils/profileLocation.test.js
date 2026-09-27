import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  editedLocationPayload,
  locationFieldValue,
  profileCity,
  profileLocationPayload
} from './profileLocation.js'

const liege = { location: 'Liège', latitude: 50.64, longitude: 5.57 }

describe('profileCity', () => {
  it('is the location with its coordinates', () => {
    assert.deepEqual(profileCity(liege), { name: 'Liège', latitude: 50.64, longitude: 5.57 })
  })

  it('is none for text typed before the city picker, or no location at all', () => {
    assert.equal(profileCity({ location: 'Quelque part dans le sud' }), null)
    assert.equal(profileCity({}), null)
    assert.equal(profileCity(null), null)
  })

  it('keeps a city on the equator or the meridian', () => {
    assert.deepEqual(profileCity({ location: 'Nulle part', latitude: 0, longitude: 0 }), {
      name: 'Nulle part',
      latitude: 0,
      longitude: 0
    })
  })
})

describe('locationFieldValue', () => {
  it('starts the picker from the city, or from the text as typed', () => {
    assert.deepEqual(locationFieldValue(liege), { name: 'Liège', latitude: 50.64, longitude: 5.57 })
    assert.equal(locationFieldValue({ location: 'Sud-Ouest' }), 'Sud-Ouest')
    assert.equal(locationFieldValue({}), null)
  })
})

describe('profileLocationPayload', () => {
  it('saves a picked city with its coordinates', () => {
    assert.deepEqual(
      profileLocationPayload({
        name: 'Liège',
        context: 'Belgique',
        latitude: 50.64,
        longitude: 5.57
      }),
      liege
    )
  })

  it('saves typed text without coordinates, and blank text as no location', () => {
    assert.deepEqual(profileLocationPayload('  Sud-Ouest '), {
      location: 'Sud-Ouest',
      latitude: null,
      longitude: null
    })
    assert.deepEqual(profileLocationPayload('   '), {
      location: null,
      latitude: null,
      longitude: null
    })
    assert.deepEqual(profileLocationPayload(null), {
      location: null,
      latitude: null,
      longitude: null
    })
  })
})

describe('editedLocationPayload', () => {
  const city = { name: 'Liège', latitude: 50.64, longitude: 5.57 }

  it('leaves the location out while the picker holds what it started from', () => {
    assert.equal(editedLocationPayload('Sud-Ouest', 'Sud-Ouest'), null)
    assert.equal(editedLocationPayload({ ...city }, city), null)
    assert.equal(editedLocationPayload(null, null), null)
  })

  it('saves a city picked over typed text, and text typed over a city', () => {
    assert.deepEqual(editedLocationPayload(city, 'Liege'), liege)
    assert.deepEqual(editedLocationPayload('Liège', city), {
      location: 'Liège',
      latitude: null,
      longitude: null
    })
  })

  it('saves a cleared location', () => {
    assert.deepEqual(editedLocationPayload('', city), {
      location: null,
      latitude: null,
      longitude: null
    })
  })
})
