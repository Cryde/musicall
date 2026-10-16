/**
 * The guided mode of the musician search (#1084): three questions, one at a time, over the same
 * search. Its rules live here so they are tested without a browser.
 */

import { i18n } from '../i18n/index.js'

export const GUIDED_MODE = 'guided'
export const GUIDED_STEPS = Object.freeze(['instrument', 'location', 'styles'])
// Getters, so each read is translated in the language of the moment.
export const STEP_LABELS = Object.freeze({
  get instrument() {
    return i18n.global.t('search.guided.step.instrument')
  },
  get location() {
    return i18n.global.t('search.guided.step.location')
  },
  get styles() {
    return i18n.global.t('search.guided.step.styles')
  }
})
export const RADIUS_OPTIONS = Object.freeze([10, 25, 50, 100])
export const DEFAULT_RADIUS = 25

/** The instruments and styles offered as one tap answers; the rest are behind « Autre ». */
export const QUICK_INSTRUMENT_SLUGS = Object.freeze([
  'batterie',
  'basse',
  'guitare',
  'chant',
  'clavier'
])
export const QUICK_STYLE_SLUGS = Object.freeze([
  'rock',
  'metal',
  'pop',
  'blues',
  'funk',
  'jazz',
  'punk',
  'grunge',
  'chanson-francaise'
])

/** The first question not answered (or skipped) yet, none once all three are. */
export function nextStep(answered) {
  return GUIDED_STEPS.find((step) => !answered.includes(step)) ?? null
}

/** The items the slugs name, in the slugs' order, leaving out one the list does not have. */
export function quickPicks(items, slugs) {
  return slugs.map((slug) => items.find((item) => item.slug === slug)).filter(Boolean)
}

/**
 * « Batteur / Batteuse » as « batteurs / batteuses »: the first word of each form takes the plural,
 * so « Joueur / Joueuse de Djembé » reads « joueurs / joueuses de djembé ».
 */
export function pluralMusicianName(name) {
  return name
    .split(' / ')
    .map((form) => {
      const [first, ...rest] = form.split(' ')
      const plural = /[sxz]$/i.test(first) ? first : `${first}s`
      return [plural, ...rest].join(' ')
    })
    .join(' / ')
    .toLocaleLowerCase()
}

const QUESTION_KEYS = {
  musician: {
    instrument: 'search.guided.question.musician_instrument',
    location: 'search.guided.question.musician_location',
    styles: 'search.guided.question.musician_styles'
  },
  band: {
    instrument: 'search.guided.question.band_instrument',
    location: 'search.guided.question.band_location',
    styles: 'search.guided.question.band_styles'
  }
}

/** The question of a step, worded for a band looking for a musician or a musician for a band. */
export function stepQuestion(step, lookingForBand) {
  return i18n.global.t(QUESTION_KEYS[lookingForBand ? 'band' : 'musician'][step])
}

/** What the member is looking for, in the plural: « batteurs / batteuses », « groupes », « musiciens ». */
export function soughtLabel({ lookingForBand, instrument }) {
  if (lookingForBand) return i18n.global.t('search.guided.sought_bands')
  return instrument
    ? pluralMusicianName(instrument.musician_name)
    : i18n.global.t('search.guided.sought_musicians')
}

/** « Bruxelles · 25 km », or the city alone when every distance is kept. */
export function locationLabel(location, radius) {
  if (!location?.name) return null
  return radius ? `${location.name} · ${radius} km` : location.name
}

/**
 * The registration card's headline: « Encore plus de batteurs / batteuses rock / métal autour de
 * Bruxelles », each part only when the search has it.
 */
export function signupHeadline({ lookingForBand, instrument, styles, location }) {
  const params = {
    sought: soughtLabel({ lookingForBand, instrument }),
    styles: styles.map((style) => style.name.toLocaleLowerCase()).join(' / '),
    city: location?.name
  }
  if (styles.length > 0 && params.city)
    return i18n.global.t('search.guided.signup_headline_styles_location', params)
  if (styles.length > 0) return i18n.global.t('search.guided.signup_headline_styles', params)
  if (params.city) return i18n.global.t('search.guided.signup_headline_location', params)
  return i18n.global.t('search.guided.signup_headline', params)
}
