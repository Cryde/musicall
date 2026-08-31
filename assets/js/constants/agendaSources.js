/**
 * Everything the frontend knows about the sources `AgendaAggregator` merges into the agenda.
 *
 * The backend emits a `source` discriminator on every `AgendaItem` and each screen reading it used
 * to carry its own partial copy of the list: the agenda page held five (filter chips, dot colours,
 * the default filter set, the counter seed, and three `switch` statements) and the dashboard widget
 * a sixth. Adding `absence` in #777 meant editing all of them, and the widget was missed, so the
 * same absence rendered violet on the agenda and grey on the dashboard. One row per source here is
 * what keeps the two screens saying the same thing.
 *
 * Same shape as `fileSources.js`, which exists for the same reason on the file module. A fifth
 * source is one row here plus, if it is clickable, one branch in `Agenda.vue`'s `handleItemClick`.
 *
 * Ordered as the filter chips read left to right.
 */

/**
 * @typedef {object} AgendaSource
 * @property {string} key The `source` the API emits.
 * @property {string} chipLabel Plural, on the filter chip: « Tâches ».
 * @property {string} label Singular, on an item's badge: « Tâche ».
 * @property {string} color Hex, for the year overview's day dots.
 * @property {string} badgeClass Badge beside an item's title, and the active filter chip.
 * @property {string} borderClass Left border of a row in the agenda list.
 * @property {string} widgetBorderClass Left border of a row in the dashboard widget, a notch lighter.
 */

/** @type {readonly AgendaSource[]} */
const AGENDA_SOURCES = Object.freeze([
  Object.freeze({
    key: 'manual',
    chipLabel: 'Manuel',
    label: 'Manuel',
    color: '#3b82f6',
    badgeClass: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    borderClass: 'border-l-blue-500',
    widgetBorderClass: 'border-blue-400'
  }),
  Object.freeze({
    key: 'task',
    chipLabel: 'Tâches',
    label: 'Tâche',
    color: '#f59e0b',
    badgeClass: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    borderClass: 'border-l-amber-500',
    widgetBorderClass: 'border-amber-400'
  }),
  Object.freeze({
    key: 'finance',
    chipLabel: 'Finances',
    label: 'Finance',
    color: '#10b981',
    badgeClass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    borderClass: 'border-l-emerald-500',
    widgetBorderClass: 'border-emerald-400'
  }),
  Object.freeze({
    key: 'absence',
    chipLabel: 'Absences',
    label: 'Absence',
    // Violet: distinct from the other three, and not red, which would read as an error rather than
    // as somebody being away.
    color: '#8b5cf6',
    badgeClass: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    borderClass: 'border-l-violet-500',
    widgetBorderClass: 'border-violet-400'
  })
])

/**
 * Stands in for a source the frontend has never heard of, so a backend that grows a fifth one
 * degrades to a neutral row instead of an unstyled one printing the raw key.
 */
const UNKNOWN_SOURCE = Object.freeze({
  key: null,
  chipLabel: null,
  label: null,
  color: '#94a3b8',
  badgeClass: 'bg-surface-100 text-surface-600',
  borderClass: 'border-l-surface-300',
  widgetBorderClass: 'border-surface-300'
})

const AGENDA_SOURCE_BY_KEY = Object.freeze(
  Object.fromEntries(AGENDA_SOURCES.map((source) => [source.key, source]))
)

/** The filter chips, in display order. */
export const AGENDA_SOURCE_LIST = AGENDA_SOURCES

/** Every key the aggregator can emit, for the default filter set and the counter seed. */
export const AGENDA_SOURCE_KEYS = Object.freeze(AGENDA_SOURCES.map((source) => source.key))

/**
 * The descriptor for a source, never null: an unknown key falls back to neutral styling.
 *
 * @param {string} key
 * @returns {AgendaSource}
 */
export function agendaSourceFor(key) {
  return AGENDA_SOURCE_BY_KEY[key] ?? UNKNOWN_SOURCE
}

/** The badge text for a source; the raw key for one the frontend does not know. */
export function agendaSourceLabel(key) {
  return agendaSourceFor(key).label ?? key
}
