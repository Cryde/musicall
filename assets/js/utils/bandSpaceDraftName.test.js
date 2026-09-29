import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { saveDraftName, takeDraftName } from './bandSpaceDraftName.js'

/**
 * The name typed on /band-space has to survive a sign up and then be used exactly once, and a
 * browser that blocks storage must cost the visitor a retyped name, never an error.
 *
 * Run with `npm test`.
 */

function fakeStorage(initial = {}) {
  const values = { ...initial }

  return {
    values,
    getItem: (key) => (key in values ? values[key] : null),
    setItem: (key, value) => {
      values[key] = value
    },
    removeItem: (key) => {
      delete values[key]
    }
  }
}

const throwingStorage = {
  getItem: () => {
    throw new Error('blocked')
  },
  setItem: () => {
    throw new Error('blocked')
  },
  removeItem: () => {
    throw new Error('blocked')
  }
}

describe('saveDraftName and takeDraftName', () => {
  it('hands the saved name back, trimmed', () => {
    const storage = fakeStorage()
    saveDraftName(storage, '  ElectricNight  ')

    assert.equal(takeDraftName(storage), 'ElectricNight')
  })

  it('gives the name only once', () => {
    const storage = fakeStorage()
    saveDraftName(storage, 'Velvet Static')
    takeDraftName(storage)

    assert.equal(takeDraftName(storage), '')
  })

  it('stores nothing for a blank name', () => {
    const storage = fakeStorage()
    saveDraftName(storage, '   ')

    assert.deepEqual(storage.values, {})
  })

  it('clears an earlier draft when the name is blanked', () => {
    const storage = fakeStorage()
    saveDraftName(storage, 'Velvet Static')
    saveDraftName(storage, '')

    assert.equal(takeDraftName(storage), '')
  })

  it('reads as empty without storage', () => {
    saveDraftName(null, 'Velvet Static')

    assert.equal(takeDraftName(null), '')
  })

  it('swallows a storage that throws', () => {
    assert.doesNotThrow(() => saveDraftName(throwingStorage, 'Velvet Static'))
    assert.equal(takeDraftName(throwingStorage), '')
  })
})
