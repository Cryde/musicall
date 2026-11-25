/**
 * « Demander un devis » (#1408): the questions each kind of service asks, mirroring ServiceKind::quoteQuestions(), the
 * answers the API takes and the message they open with. Kept out of the component so they are tested.
 */

import { kindSpecialities } from './visualServicePage.js'

/** Mirrors the Range on `tracks` in ServiceKind::quoteQuestions(). */
export const MAX_TRACKS = 100
/** Mirrors the Length on `city` in ServiceKind::quoteQuestions(). */
export const MAX_CITY_LENGTH = 100
const CALENDAR_DAY = /^\d{4}-\d{2}-\d{2}$/

// Full literal keys, so the catalogue test sees every one of them (assets/js/i18n/fr.test.js).
export const QUESTION_LABELS = {
  need: 'profile.quote_request.question.need',
  tracks: 'profile.quote_request.question.tracks',
  link: 'profile.quote_request.question.link',
  deadline: 'profile.quote_request.question.deadline',
  date: 'profile.quote_request.question.date',
  city: 'profile.quote_request.question.city',
  budget: 'profile.quote_request.question.budget'
}
/** Every kind's needs in one map: a value means the same wherever it is asked. */
export const OPTION_LABELS = {
  need: {
    mixing: 'profile.quote_request.need.mixing',
    mastering: 'profile.quote_request.need.mastering',
    recording: 'profile.quote_request.need.recording',
    live: 'profile.quote_request.need.live',
    concert: 'profile.quote_request.need.concert',
    promo: 'profile.quote_request.need.promo',
    cover: 'profile.quote_request.need.cover',
    clip: 'profile.quote_request.need.clip',
    live_session: 'profile.quote_request.need.live_session',
    concert_film: 'profile.quote_request.need.concert_film',
    logo: 'profile.quote_request.need.logo',
    poster: 'profile.quote_request.need.poster',
    merch: 'profile.quote_request.need.merch',
    visual_identity: 'profile.quote_request.need.visual_identity',
    social_media: 'profile.quote_request.need.social_media',
    band_site: 'profile.quote_request.need.band_site',
    epk: 'profile.quote_request.need.epk',
    online_store: 'profile.quote_request.need.online_store',
    redesign_maintenance: 'profile.quote_request.need.redesign_maintenance'
  },
  budget: {
    under_100: 'profile.quote_request.budget.under_100',
    '100_to_300': 'profile.quote_request.budget.100_to_300',
    '300_to_1000': 'profile.quote_request.budget.300_to_1000',
    over_1000: 'profile.quote_request.budget.over_1000'
  }
}
const MESSAGE = {
  greeting: 'profile.quote_request.message.greeting',
  need: 'profile.quote_request.message.need',
  tracks: 'profile.quote_request.message.tracks',
  link: 'profile.quote_request.message.link',
  deadline: 'profile.quote_request.message.deadline',
  date: 'profile.quote_request.message.date',
  city: 'profile.quote_request.message.city',
  budget: 'profile.quote_request.message.budget'
}

const BUDGET = { key: 'budget', type: 'choice', options: Object.keys(OPTION_LABELS.budget) }
const need = (options) => ({ key: 'need', type: 'choice', required: true, options })
/** A photographer or a videographer (#1411): what, then when and where it happens. */
const eventQuestions = (needs) => [
  need(needs),
  { key: 'date', type: 'date' },
  { key: 'city', type: 'text', maxLength: MAX_CITY_LENGTH },
  BUDGET
]
/** A graphic designer or a webmaster (#1412): what, a link to the music it is for, and when; never where. */
const projectQuestions = (needs) => [
  need(needs),
  { key: 'link', type: 'link' },
  { key: 'deadline', type: 'date' },
  BUDGET
]

/** By kind, in asking order; only `need` is required. The budget is asked of every kind. */
const QUESTIONS = {
  sound_engineering: [
    need(['mixing', 'mastering', 'recording', 'live']),
    { key: 'tracks', type: 'number' },
    { key: 'link', type: 'link' },
    { key: 'deadline', type: 'date' },
    BUDGET
  ],
  photography: eventQuestions(['concert', 'promo', 'cover']),
  video: eventQuestions(['clip', 'live_session', 'concert_film']),
  graphic_design: projectQuestions(kindSpecialities('graphic_design')),
  web: projectQuestions(kindSpecialities('web'))
}

/** The questions a kind asks, none for a kind that takes no quote request (teaching asks for a lesson instead). */
export function quoteQuestions(kind) {
  return QUESTIONS[kind] ?? []
}

/** Where the answers wait while the visitor signs in: one request per member and kind. */
export function quoteDraftKey(kind, username) {
  return `quote:${kind}:${username}`
}

/** Whether `value` answers `question`: an option it offers, a track count in range, a calendar day, some text. */
function isAnswer(question, value) {
  switch (question.type) {
    case 'choice':
      return question.options.includes(value)
    case 'number':
      return Number.isInteger(value) && value >= 1 && value <= MAX_TRACKS
    case 'date':
      return typeof value === 'string' && CALENDAR_DAY.test(value)
    default:
      return (
        typeof value === 'string' &&
        value.trim() !== '' &&
        value.trim().length <= (question.maxLength ?? Number.POSITIVE_INFINITY)
      )
  }
}

/**
 * The answers as the API takes them, in asking order: only the valid ones, the link trimmed. Also what a draft kept
 * from before signing in is read through, since the options may have changed since.
 */
export function quoteAnswers(answers, questions) {
  const given = {}
  for (const question of questions) {
    const value = answers?.[question.key]
    if (isAnswer(question, value))
      given[question.key] = typeof value === 'string' ? value.trim() : value
  }
  return given
}

/** Whether every required question has an answer. */
export function hasRequiredAnswers(answers, questions) {
  const given = quoteAnswers(answers, questions)
  return questions.every((question) => !question.required || Object.hasOwn(given, question.key))
}

/** Sent with the message, so the engineer's card reads « Devis : mixage » (#1408). */
export function quoteContactOrigin(serviceId, answers, questions) {
  return { serviceId, quote: quoteAnswers(answers, questions) }
}

/**
 * The message's opening lines: a greeting, then one labelled line per answer, as the member reads them at a glance.
 *
 * @param {object} answers   question key to answer
 * @param {object} context   { name, questions, formatDate }: formatDate('2026-12-15') names a day
 * @param {(key: string, params?: object) => string} t  vue-i18n's t
 */
export function buildQuoteMessage(answers, context, t) {
  const lines = [t(MESSAGE.greeting, { name: context.name })]
  const types = Object.fromEntries(
    context.questions.map((question) => [question.key, question.type])
  )
  for (const [key, value] of Object.entries(quoteAnswers(answers, context.questions))) {
    if (OPTION_LABELS[key]) {
      lines.push(t(MESSAGE[key], { value: t(OPTION_LABELS[key][value]) }))
    } else if (types[key] === 'date') {
      lines.push(t(MESSAGE[key], { value: context.formatDate(value) }))
    } else {
      lines.push(t(MESSAGE[key], { value }))
    }
  }
  return lines.join('\n')
}
