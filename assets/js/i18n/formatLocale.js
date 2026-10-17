import { enGB, fr } from 'date-fns/locale'
import { DEFAULT_LOCALE, i18n } from './index.js'

// What each app locale formats dates, numbers and amounts with: the BCP 47 tag for Intl, toLocale*
// and localeCompare, and the date-fns locale. `fr-FR` and `fr` format identically, so one tag serves.
// English is British: weeks start on Monday and the day comes before the month.
// A locale enabled without an entry here formats in French.
//
// `datePatterns` are the date-fns patterns that carry a word, so each language needs its own.
// `marks` are what the two languages punctuate differently: French spaces its colon and its percent
// sign, and where French sets a middle dot, an arrow or a dash, the English text uses a plain hyphen.
const FORMATS = {
  fr: {
    tag: 'fr-FR',
    dateFns: fr,
    datePatterns: {
      dayMonthTime: "d MMMM 'à' HH:mm",
      dayMonthYearTime: "d MMMM yyyy 'à' HH:mm",
      weekdayDateTime: "EEEE d MMMM yyyy 'à' HH:mm"
    },
    byteUnits: ['o', 'Ko', 'Mo', 'Go'],
    marks: {
      separator: '·',
      rangeArrow: '→',
      rangeDash: '–',
      noValue: '—',
      indent: '— ',
      colon: '\u00a0:',
      percent: ' %'
    }
  },
  en: {
    tag: 'en-GB',
    dateFns: enGB,
    datePatterns: {
      dayMonthTime: "d MMMM 'at' HH:mm",
      dayMonthYearTime: "d MMMM yyyy 'at' HH:mm",
      weekdayDateTime: "EEEE d MMMM yyyy 'at' HH:mm"
    },
    byteUnits: ['B', 'KB', 'MB', 'GB'],
    marks: {
      separator: '-',
      rangeArrow: '-',
      rangeDash: '-',
      noValue: '-',
      indent: '- ',
      colon: ':',
      percent: '%'
    }
  }
}

/** The entry of a table keyed by app locale (`fr`, ...) for the active vue-i18n locale. */
export function forActiveLocale(table) {
  return table[i18n.global.locale.value] ?? table[DEFAULT_LOCALE]
}

/** The BCP 47 tag to hand to Intl, toLocale* and localeCompare. */
export function intlLocale() {
  return forActiveLocale(FORMATS).tag
}

/** The locale object to hand to date-fns. */
export function dateFnsLocale() {
  return forActiveLocale(FORMATS).dateFns
}

/** A date-fns pattern by name: `dayMonthTime`, `dayMonthYearTime` or `weekdayDateTime`. */
export function datePattern(name) {
  return forActiveLocale(FORMATS).datePatterns[name]
}

/** The units of a byte count, from bytes to gigabytes. */
export function byteUnits() {
  return forActiveLocale(FORMATS).byteUnits
}

/** Between two facts on one line, where French sets a middle dot. */
export function separator() {
  return forActiveLocale(FORMATS).marks.separator
}

/** Between the two ends of a range French draws with an arrow (an absence, a time slot). */
export function rangeArrow() {
  return forActiveLocale(FORMATS).marks.rangeArrow
}

/** Between the two ends of a range French draws with an en dash (an amount, a period). */
export function rangeDash() {
  return forActiveLocale(FORMATS).marks.rangeDash
}

/** What stands in for a missing value, an em dash in French. */
export function noValue() {
  return forActiveLocale(FORMATS).marks.noValue
}

/** The marks before a folder's name in a select, one per level of depth. */
export function treeIndent(depth) {
  return forActiveLocale(FORMATS).marks.indent.repeat(depth)
}

/** The colon after a label, with the space French puts before it. */
export function colon() {
  return forActiveLocale(FORMATS).marks.colon
}

/** A percentage, its sign spaced the way the language spaces it: "12.5 %" in French. */
export function formatPercent(value) {
  return `${value}${forActiveLocale(FORMATS).marks.percent}`
}
