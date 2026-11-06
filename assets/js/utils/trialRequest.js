/**
 * « Demander un cours d'essai » (#1311): which questions a teacher's profile allows, and the opening lines they
 * write. Kept out of the component so they are tested.
 */
import { DAYS_OF_WEEK } from './teacherPage.js'

const LEVELS = ['beginner', 'intermediate', 'advanced']
const PLACES = ['teacher_place', 'student_place', 'online']
const PARTS_OF_DAY = ['morning', 'afternoon', 'evening']
const DRAFT_PREFIX = 'trial-request:'
// Long enough to sign in or sign up and come back, short enough not to greet the next person in a shared tab.
export const DRAFT_LIFETIME_MS = 2 * 60 * 60 * 1000

// Full literal keys, so the catalogue test sees every one of them (assets/js/i18n/fr.test.js).
const QUESTION_LABELS = {
  instrument: 'profile.teacher_request.question.instrument',
  level: 'profile.teacher_request.question.level',
  who: 'profile.teacher_request.question.who',
  place: 'profile.teacher_request.question.place',
  when: 'profile.teacher_request.question.when'
}
const LEVEL_LABELS = {
  beginner: 'profile.teacher_request.level.beginner',
  intermediate: 'profile.teacher_request.level.intermediate',
  advanced: 'profile.teacher_request.level.advanced'
}
const WHO_LABELS = {
  child: 'profile.teacher_request.who.child',
  teen: 'profile.teacher_request.who.teen',
  me: 'profile.teacher_request.who.me'
}
const PLACE_LABELS = {
  teacher_place_city: 'profile.teacher_request.place.teacher_place_city',
  teacher_place: 'profile.teacher_request.place.teacher_place',
  student_place: 'profile.teacher_request.place.student_place',
  online: 'profile.teacher_request.place.online'
}
export const PART_OF_DAY_LABELS = {
  morning: 'profile.teacher_request.part.morning',
  afternoon: 'profile.teacher_request.part.afternoon',
  evening: 'profile.teacher_request.part.evening'
}
export const WHEN_OPTION_LABEL = 'profile.teacher_request.when_option'
const MESSAGE = {
  greetingTrial: 'profile.teacher_request.message.greeting_trial',
  greetingFirst: 'profile.teacher_request.message.greeting_first',
  instrument: 'profile.teacher_request.message.instrument',
  level: 'profile.teacher_request.message.level',
  who: 'profile.teacher_request.message.who',
  place: 'profile.teacher_request.message.place',
  when: 'profile.teacher_request.message.when'
}
const LEVEL_VALUES = {
  beginner: 'profile.teacher_request.message.level_value.beginner',
  intermediate: 'profile.teacher_request.message.level_value.intermediate',
  advanced: 'profile.teacher_request.message.level_value.advanced'
}
const WHO_VALUES = {
  child: 'profile.teacher_request.message.who_value.child',
  teen: 'profile.teacher_request.message.who_value.teen',
  me: 'profile.teacher_request.message.who_value.me'
}
const PLACE_VALUES = {
  teacher_place_city: 'profile.teacher_request.message.place_value.teacher_place_city',
  teacher_place: 'profile.teacher_request.message.place_value.teacher_place',
  student_place: 'profile.teacher_request.message.place_value.student_place',
  online: 'profile.teacher_request.message.place_value.online'
}

/** Before noon is the morning, before five the afternoon, the rest the evening. */
export function partOfDay(time) {
  const hour = Number.parseInt(String(time).slice(0, 2), 10)
  if (hour < 12) return 'morning'
  if (hour < 17) return 'afternoon'
  return 'evening'
}

/**
 * The questions this teacher's profile can answer, each with only the options they offer, in asking order. A
 * question without an option is left out; one instrument needs no question.
 *
 * A question carries its `labelKey`; options carry a `value` and either a ready `label` (an instrument name) or a
 * `labelKey` with `params`.
 */
export function requestQuestions(profile) {
  const questions = []

  const instruments = profile?.instruments ?? []
  if (instruments.length > 1) {
    questions.push({
      key: 'instrument',
      labelKey: QUESTION_LABELS.instrument,
      options: instruments.map((item) => ({
        value: item.instrument_id,
        label: item.instrument_name
      }))
    })
  }

  const levels = LEVELS.filter((level) => profile?.student_levels?.includes(level))
  if (levels.length) {
    questions.push({
      key: 'level',
      labelKey: QUESTION_LABELS.level,
      options: levels.map((level) => ({ value: level, labelKey: LEVEL_LABELS[level] }))
    })
  }

  const groups = profile?.age_groups ?? []
  const who = [
    groups.includes('children') && 'child',
    groups.includes('teenagers') && 'teen',
    (groups.includes('adults') || groups.includes('seniors')) && 'me'
  ].filter(Boolean)
  if (who.length) {
    questions.push({
      key: 'who',
      labelKey: QUESTION_LABELS.who,
      options: who.map((value) => ({ value, labelKey: WHO_LABELS[value] }))
    })
  }

  const locations = profile?.locations ?? []
  const places = PLACES.filter((type) => locations.some((place) => place.type === type))
  if (places.length) {
    const city =
      locations.find((place) => place.type === 'teacher_place' && place.city)?.city ?? null
    questions.push({
      key: 'place',
      labelKey: QUESTION_LABELS.place,
      options: places.map((type) => ({
        value: type,
        labelKey:
          type === 'teacher_place' && city ? PLACE_LABELS.teacher_place_city : PLACE_LABELS[type],
        params: { city }
      }))
    })
  }

  const slots = profile?.availability ?? []
  const when = []
  for (const day of DAYS_OF_WEEK) {
    const parts = new Set(
      slots.filter((slot) => slot.day_of_week === day).map((slot) => partOfDay(slot.start_time))
    )
    for (const part of PARTS_OF_DAY) {
      if (parts.has(part))
        when.push({ value: `${day}_${part}`, labelKey: WHEN_OPTION_LABEL, params: { day, part } })
    }
  }
  if (when.length) {
    questions.push({ key: 'when', labelKey: QUESTION_LABELS.when, options: when })
  }

  return questions
}

/**
 * The message's opening lines: a greeting, then one labelled line per answer, as a teacher reads them at a glance.
 * A list rather than a sentence, which would have to bend around each instrument name.
 *
 * @param {object} answers   question key to option value
 * @param {object} context   { name, offersTrial, questions, instrumentName, whenLabel }: instrumentName names a
 *                            single instrument, whenLabel({ day, part }) names a time slot as the options do
 * @param {(key: string, params?: object) => string} t  vue-i18n's t
 */
export function buildRequestMessage(answers, context, t) {
  const lines = [
    t(context.offersTrial ? MESSAGE.greetingTrial : MESSAGE.greetingFirst, { name: context.name })
  ]
  const optionOf = (key) =>
    context.questions
      .find((question) => question.key === key)
      ?.options.find((option) => option.value === answers[key])

  const instrument = optionOf('instrument')?.label ?? context.instrumentName
  if (instrument) lines.push(t(MESSAGE.instrument, { value: instrument }))

  if (optionOf('level')) lines.push(t(MESSAGE.level, { value: t(LEVEL_VALUES[answers.level]) }))
  if (optionOf('who')) lines.push(t(MESSAGE.who, { value: t(WHO_VALUES[answers.who]) }))

  const place = optionOf('place')
  if (place) {
    const city = place.params?.city
    const value =
      answers.place === 'teacher_place' && city
        ? t(PLACE_VALUES.teacher_place_city, { city })
        : t(PLACE_VALUES[answers.place])
    lines.push(t(MESSAGE.place, { value }))
  }

  const when = optionOf('when')
  if (when) lines.push(t(MESSAGE.when, { value: context.whenLabel(when.params) }))

  return lines.join('\n')
}

/** The answers a visitor gave before signing in, kept in this tab only. */
export function saveRequestDraft(storage, username, draft, now = Date.now()) {
  if (!storage || !username) return
  try {
    storage.setItem(
      DRAFT_PREFIX + username.toLowerCase(),
      JSON.stringify({ ...draft, savedAt: now })
    )
  } catch {
    // Storage full or blocked: the visitor answers again.
  }
}

/** Taken once, and only while it is fresh. */
export function takeRequestDraft(storage, username, now = Date.now()) {
  if (!storage || !username) return null
  const key = DRAFT_PREFIX + username.toLowerCase()
  try {
    const raw = storage.getItem(key)
    storage.removeItem(key)
    if (!raw) return null
    const draft = JSON.parse(raw)
    if (typeof draft?.savedAt !== 'number' || now - draft.savedAt > DRAFT_LIFETIME_MS) return null
    return {
      answers: draft.answers ?? {},
      message: typeof draft.message === 'string' ? draft.message : null
    }
  } catch {
    return null
  }
}

/** Whether a draft waits for this teacher, without taking it. */
export function hasRequestDraft(storage, username, now = Date.now()) {
  if (!storage || !username) return false
  try {
    const raw = storage.getItem(DRAFT_PREFIX + username.toLowerCase())
    const draft = raw ? JSON.parse(raw) : null
    return typeof draft?.savedAt === 'number' && now - draft.savedAt <= DRAFT_LIFETIME_MS
  } catch {
    return false
  }
}

/** Every request draft in this tab, at logout: the next person to sign in here must not find them. */
export function clearRequestDrafts(storage) {
  if (!storage) return
  try {
    const keys = []
    for (let index = 0; index < storage.length; index++) {
      const key = storage.key(index)
      if (key?.startsWith(DRAFT_PREFIX)) keys.push(key)
    }
    for (const key of keys) storage.removeItem(key)
  } catch {
    // Blocked storage holds no draft.
  }
}

/** Reading the property itself throws when the browser blocks storage. */
export function openRequestDraftStorage() {
  try {
    return globalThis.sessionStorage ?? null
  } catch {
    return null
  }
}
