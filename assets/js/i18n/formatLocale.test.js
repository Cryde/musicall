import assert from 'node:assert/strict'
import { afterEach, describe, it } from 'node:test'
import { enGB, fr } from 'date-fns/locale'
import {
  byteUnits,
  colon,
  dateFnsLocale,
  datePattern,
  forActiveLocale,
  formatPercent,
  intlLocale,
  noValue,
  rangeArrow,
  rangeDash,
  separator,
  treeIndent
} from './formatLocale.js'
import { i18n } from './index.js'

describe('formatLocale', () => {
  afterEach(() => {
    i18n.global.locale.value = 'fr'
  })

  it('formats French with the tag and the date-fns locale the app always used', () => {
    assert.equal(intlLocale(), 'fr-FR')
    assert.equal(dateFnsLocale(), fr)
  })

  it('formats English the British way', () => {
    i18n.global.locale.value = 'en'

    assert.equal(intlLocale(), 'en-GB')
    assert.equal(dateFnsLocale(), enGB)
    assert.equal(dateFnsLocale().options.weekStartsOn, 1)
  })

  it('picks the entry of the active locale', () => {
    const table = { fr: 'français', en: 'English' }

    assert.equal(forActiveLocale(table), 'français')
    i18n.global.locale.value = 'en'
    assert.equal(forActiveLocale(table), 'English')
  })

  it('falls back on French for a locale with no entry', () => {
    i18n.global.locale.value = 'de'

    assert.equal(forActiveLocale({ fr: 'français' }), 'français')
    assert.equal(intlLocale(), 'fr-FR')
    assert.equal(noValue(), '—')
  })

  it('keeps the date patterns French wrote with a word in them', () => {
    assert.equal(datePattern('dayMonthTime'), "d MMMM 'à' HH:mm")
    assert.equal(datePattern('dayMonthYearTime'), "d MMMM yyyy 'à' HH:mm")
    assert.equal(datePattern('weekdayDateTime'), "EEEE d MMMM yyyy 'à' HH:mm")
  })

  it('gives English its own word in the date patterns', () => {
    i18n.global.locale.value = 'en'

    assert.equal(datePattern('dayMonthTime'), "d MMMM 'at' HH:mm")
    assert.equal(datePattern('dayMonthYearTime'), "d MMMM yyyy 'at' HH:mm")
    assert.equal(datePattern('weekdayDateTime'), "EEEE d MMMM yyyy 'at' HH:mm")
  })

  it('names the byte units in the language', () => {
    assert.deepEqual(byteUnits(), ['o', 'Ko', 'Mo', 'Go'])
    i18n.global.locale.value = 'en'
    assert.deepEqual(byteUnits(), ['B', 'KB', 'MB', 'GB'])
  })

  it('keeps the French marks as they were', () => {
    assert.equal(separator(), '·')
    assert.equal(rangeArrow(), '→')
    assert.equal(rangeDash(), '–')
    assert.equal(noValue(), '—')
    assert.equal(colon(), '\u00a0:')
    assert.equal(formatPercent(12.5), '12.5 %')
    assert.equal(treeIndent(2), '— — ')
  })

  it('sets a plain hyphen in English where French has a dot, an arrow or a dash', () => {
    i18n.global.locale.value = 'en'

    assert.equal(separator(), '-')
    assert.equal(rangeArrow(), '-')
    assert.equal(rangeDash(), '-')
    assert.equal(noValue(), '-')
    assert.equal(colon(), ':')
    assert.equal(formatPercent(12.5), '12.5%')
    assert.equal(treeIndent(2), '- - ')
  })
})
