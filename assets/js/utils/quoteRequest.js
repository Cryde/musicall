/**
 * « Demander un devis » (#1408): the questions each kind of service asks, mirroring ServiceKind::quoteQuestions(), the
 * answers the API takes and the message they open with. Kept out of the component so they are tested.
 */

/** Mirrors the Range on `tracks` in ServiceKind::quoteQuestions(). */
export const MAX_TRACKS = 100
const CALENDAR_DAY = /^\d{4}-\d{2}-\d{2}$/

// Full literal keys, so the catalogue test sees every one of them (assets/js/i18n/fr.test.js).
export const QUESTION_LABELS = {
  need: 'profile.quote_request.question.need',
  tracks: 'profile.quote_request.question.tracks',
  link: 'profile.quote_request.question.link',
  deadline: 'profile.quote_request.question.deadline',
  budget: 'profile.quote_request.question.budget'
}
export const OPTION_LABELS = {
  need: {
    mixing: 'profile.quote_request.need.mixing',
    mastering: 'profile.quote_request.need.mastering',
    recording: 'profile.quote_request.need.recording',
    live: 'profile.quote_request.need.live'
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
  budget: 'profile.quote_request.message.budget'
}

const BUDGET = { key: 'budget', type: 'choice', options: Object.keys(OPTION_LABELS.budget) }

/** By kind, in asking order; only `need` is required. The budget is asked of every kind. */
const QUESTIONS = {
  sound_engineering: [
    { key: 'need', type: 'choice', required: true, options: Object.keys(OPTION_LABELS.need) },
    { key: 'tracks', type: 'number' },
    { key: 'link', type: 'link' },
    { key: 'deadline', type: 'date' },
    BUDGET
  ]
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
      return typeof value === 'string' && value.trim() !== ''
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
 * The message's opening lines: a greeting, then one labelled line per answer, as the engineer reads them at a glance.
 *
 * @param {object} answers   question key to answer
 * @param {object} context   { name, questions, formatDeadline }: formatDeadline('2026-12-15') names the day
 * @param {(key: string, params?: object) => string} t  vue-i18n's t
 */
export function buildQuoteMessage(answers, context, t) {
  const lines = [t(MESSAGE.greeting, { name: context.name })]
  for (const [key, value] of Object.entries(quoteAnswers(answers, context.questions))) {
    if (OPTION_LABELS[key]) {
      lines.push(t(MESSAGE[key], { value: t(OPTION_LABELS[key][value]) }))
    } else if (key === 'deadline') {
      lines.push(t(MESSAGE.deadline, { value: context.formatDeadline(value) }))
    } else {
      lines.push(t(MESSAGE[key], { value }))
    }
  }
  return lines.join('\n')
}
