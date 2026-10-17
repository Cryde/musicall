import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { PRIME_VUE_CALENDAR_LOCALES } from './primeVueLocale.js'

describe('PRIME_VUE_CALENDAR_LOCALES', () => {
  const { fr, en } = PRIME_VUE_CALENDAR_LOCALES

  it('gives every locale the same keys, so a switch replaces them all', () => {
    assert.deepEqual(Object.keys(en).sort(), Object.keys(fr).sort())
  })

  it('starts the weeks on Monday and puts the day first in both languages', () => {
    for (const locale of [fr, en]) {
      assert.equal(locale.firstDayOfWeek, 1)
      assert.equal(locale.dateFormat, 'dd/mm/yy')
    }
  })

  it('names seven days and twelve months in English', () => {
    for (const key of ['dayNames', 'dayNamesShort', 'dayNamesMin']) {
      assert.equal(en[key].length, 7, key)
    }
    for (const key of ['monthNames', 'monthNamesShort']) {
      assert.equal(en[key].length, 12, key)
    }
    assert.equal(en.dayNames[1], 'Monday')
    assert.equal(en.monthNames[0], 'January')
  })
})
