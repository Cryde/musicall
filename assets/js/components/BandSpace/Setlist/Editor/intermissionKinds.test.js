import assert from 'node:assert/strict'
import { afterEach, before, describe, it } from 'node:test'
import { i18n } from '../../../../i18n/index.js'
import { intermissionKind, isDefaultIntermissionLabel } from './intermissionKinds.js'

describe('isDefaultIntermissionLabel', () => {
  // A second language whatever the English catalogue says, so the test pins the rule, not a word.
  before(() => {
    i18n.global.setLocaleMessage('xx', {
      band_space_setlist: { intermission: { break_label: 'Break', talk_label: 'Talk' } }
    })
  })

  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  it('reads the default of the current language as the default', () => {
    assert.equal(isDefaultIntermissionLabel('break', 'Pause'), true)
  })

  // A French member adds a break, an English member reads the list: still the default.
  it('reads the default of another language as the default too', () => {
    assert.equal(isDefaultIntermissionLabel('break', 'Break'), true)
    i18n.global.locale.value = 'xx'
    assert.equal(isDefaultIntermissionLabel('break', 'Pause'), true)
  })

  it('keeps a label the band wrote itself', () => {
    assert.equal(isDefaultIntermissionLabel('break', 'Changement de guitare'), false)
    assert.equal(isDefaultIntermissionLabel('break', 'Talk'), false)
  })

  it('labels a kind in the current language', () => {
    assert.equal(intermissionKind('break').label, 'Pause')
    i18n.global.locale.value = 'xx'
    assert.equal(intermissionKind('break').label, 'Break')
  })
})
