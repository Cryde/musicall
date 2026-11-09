import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  BAND_SPACE_CREATE_ACTION,
  bandSpaceCreateAction,
  bandSpaceCreateName
} from './bandSpaceCreateAction.js'
import { isOnPendingActionPage, keepPendingAction, readPendingAction } from './pendingAction.js'

/**
 * A visitor who starts creating a Band Space signs up first: the name has to be waiting in the create dialog
 * afterwards, on the page they come back to. Run with `npm test`.
 */

const NOW = Date.UTC(2026, 10, 8, 12, 0, 0)

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

describe('bandSpaceCreateAction', () => {
  it('keeps the trimmed name, to reopen the dialog on the band layout on its own', () => {
    assert.deepEqual(bandSpaceCreateAction('  ElectricNight  ', { query: {} }, '/band'), {
      kind: BAND_SPACE_CREATE_ACTION,
      route: '/band',
      payload: { name: 'ElectricNight' },
      stoppedOnSubmit: true
    })
  })

  // The home block has no field: the dialog still reopens, empty.
  it('keeps an empty name when the visitor typed none', () => {
    assert.deepEqual(bandSpaceCreateAction('', {}, '/band').payload, { name: '' })
  })

  // The visitor came for that page: the dialog is offered there, not opened over it.
  it('resumes on the deep link the page was handed, as an offer', () => {
    const action = bandSpaceCreateAction(
      'ElectricNight',
      { query: { return_url: '/band/1/agenda?view=month' } },
      '/band'
    )

    assert.equal(action.route, '/band/1/agenda?view=month')
    assert.equal(action.stoppedOnSubmit, false)
  })

  it('resumes on the band layout rather than on a handed return that leaves the site', () => {
    const action = bandSpaceCreateAction(
      'ElectricNight',
      { query: { return_url: 'https://evil.example' } },
      '/band'
    )

    assert.equal(action.route, '/band')
    assert.equal(action.stoppedOnSubmit, true)
  })

  it('is kept as a pending action for the page it resumes on, not the one it started on', () => {
    const storage = fakeStorage()
    const action = bandSpaceCreateAction('ElectricNight', { fullPath: '/band-space' }, '/band')

    assert.equal(keepPendingAction(storage, action, { isVisitor: true, now: NOW }), true)
    const kept = readPendingAction(storage, NOW + 1000)
    assert.equal(isOnPendingActionPage(kept, '/band'), true)
    assert.equal(isOnPendingActionPage(kept, '/band-space'), false)
    assert.equal(bandSpaceCreateName(kept.payload), 'ElectricNight')
  })
})

describe('bandSpaceCreateName', () => {
  it('hands back the kept name', () => {
    assert.equal(bandSpaceCreateName({ name: 'Velvet Static' }), 'Velvet Static')
  })

  it('prefills nothing from a payload that does not hold a name', () => {
    for (const payload of [null, undefined, {}, { name: 42 }, { name: ['Velvet'] }, 'Velvet']) {
      assert.equal(bandSpaceCreateName(payload), '', JSON.stringify(payload))
    }
  })
})
