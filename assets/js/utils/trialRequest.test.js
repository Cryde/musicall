import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  buildRequestMessage,
  clearRequestDrafts,
  DRAFT_LIFETIME_MS,
  hasRequestDraft,
  partOfDay,
  requestQuestions,
  saveRequestDraft,
  takeRequestDraft
} from './trialRequest.js'

const whenLabel = ({ day, part }) => `${day} ${part}`

const FULL_PROFILE = {
  instruments: [
    { instrument_id: 'g', instrument_name: 'Guitare' },
    { instrument_id: 'b', instrument_name: 'Basse' }
  ],
  student_levels: ['advanced', 'beginner'],
  age_groups: ['adults', 'seniors', 'children'],
  locations: [{ type: 'online' }, { type: 'teacher_place', city: 'Ixelles' }],
  availability: [
    { day_of_week: 'wednesday', start_time: '14:00', end_time: '16:00' },
    { day_of_week: 'monday', start_time: '18:00', end_time: '21:00' },
    { day_of_week: 'wednesday', start_time: '15:00', end_time: '17:00' }
  ]
}

/** A translator that shows what it was asked, so the message structure is what the test reads. */
const t = (fullKey, params = {}) => {
  const key = fullKey.replace('profile.teacher_request.message.', '')
  const values = Object.entries(params)
    .filter(([, value]) => value !== null && value !== undefined)
    .map(([name, value]) => `${name}=${value}`)
  return values.length ? `${key}(${values.join(',')})` : key
}

function memoryStorage() {
  const items = new Map()
  return {
    get length() {
      return items.size
    },
    key: (index) => [...items.keys()][index] ?? null,
    getItem: (key) => (items.has(key) ? items.get(key) : null),
    setItem: (key, value) => items.set(key, String(value)),
    removeItem: (key) => items.delete(key),
    items
  }
}

describe('partOfDay', () => {
  it('splits the day at noon and five', () => {
    assert.equal(partOfDay('09:30'), 'morning')
    assert.equal(partOfDay('12:00'), 'afternoon')
    assert.equal(partOfDay('16:59'), 'afternoon')
    assert.equal(partOfDay('17:00'), 'evening')
  })
})

describe('requestQuestions', () => {
  it('asks only what the teacher offers, in asking order', () => {
    const questions = requestQuestions(FULL_PROFILE)
    assert.deepEqual(
      questions.map((question) => question.key),
      ['instrument', 'level', 'who', 'place', 'when']
    )
    assert.deepEqual(
      questions[1].options.map((option) => option.value),
      ['beginner', 'advanced']
    )
    assert.deepEqual(
      questions[2].options.map((option) => option.value),
      ['child', 'me']
    )
    assert.deepEqual(questions[3].options, [
      {
        value: 'teacher_place',
        labelKey: 'profile.teacher_request.place.teacher_place_city',
        params: { city: 'Ixelles' }
      },
      {
        value: 'online',
        labelKey: 'profile.teacher_request.place.online',
        params: { city: 'Ixelles' }
      }
    ])
    assert.deepEqual(
      questions[4].options.map((option) => option.value),
      ['monday_evening', 'wednesday_afternoon']
    )
  })

  it('skips the instrument question for one instrument, and empty questions', () => {
    const questions = requestQuestions({
      instruments: [{ instrument_id: 'g', instrument_name: 'Guitare' }],
      student_levels: ['beginner'],
      age_groups: [],
      locations: [{ type: 'student_place', city: 'Uccle' }],
      availability: []
    })
    assert.deepEqual(
      questions.map((question) => question.key),
      ['level', 'place']
    )
    assert.equal(questions[1].options[0].labelKey, 'profile.teacher_request.place.student_place')
  })

  it('has nothing to ask of an empty profile', () => {
    assert.deepEqual(requestQuestions({}), [])
    assert.deepEqual(requestQuestions(undefined), [])
  })
})

describe('buildRequestMessage', () => {
  const questions = requestQuestions(FULL_PROFILE)

  it('writes a greeting then one line per answer', () => {
    const message = buildRequestMessage(
      {
        instrument: 'b',
        level: 'beginner',
        who: 'me',
        place: 'teacher_place',
        when: 'wednesday_afternoon'
      },
      { name: 'Julien', offersTrial: true, questions, whenLabel },
      t
    )
    assert.equal(
      message,
      [
        'greeting_trial(name=Julien)',
        'instrument(value=Basse)',
        'level(value=level_value.beginner)',
        'who(value=who_value.me)',
        'place(value=place_value.teacher_place_city(city=Ixelles))',
        'when(value=wednesday afternoon)'
      ].join('\n')
    )
  })

  it('asks for a first lesson when there is no trial, and leaves out what was not answered', () => {
    const message = buildRequestMessage(
      { place: 'online' },
      { name: 'Julien', offersTrial: false, questions },
      t
    )
    assert.equal(
      message,
      ['greeting_first(name=Julien)', 'place(value=place_value.online)'].join('\n')
    )
  })

  it('names the only instrument without asking for it', () => {
    const message = buildRequestMessage(
      {},
      { name: 'Julien', offersTrial: true, questions: [], instrumentName: 'Guitare' },
      t
    )
    assert.equal(message, ['greeting_trial(name=Julien)', 'instrument(value=Guitare)'].join('\n'))
  })

  it('ignores an answer the profile no longer offers', () => {
    const message = buildRequestMessage(
      { level: 'intermediate' },
      { name: 'Julien', offersTrial: true, questions },
      t
    )
    assert.equal(message, 'greeting_trial(name=Julien)')
  })
})

describe('request draft', () => {
  it('gives the answers back once, for the same teacher whatever the case', () => {
    const storage = memoryStorage()
    saveRequestDraft(
      storage,
      'Julien',
      { answers: { level: 'beginner' }, message: 'Bonjour' },
      1000
    )
    assert.equal(hasRequestDraft(storage, 'julien', 2000), true)
    assert.deepEqual(takeRequestDraft(storage, 'julien', 2000), {
      answers: { level: 'beginner' },
      message: 'Bonjour'
    })
    assert.equal(takeRequestDraft(storage, 'julien', 2000), null)
    assert.equal(hasRequestDraft(storage, 'julien', 2000), false)
  })

  it('forgets a draft older than a day, and keeps teachers apart', () => {
    const storage = memoryStorage()
    saveRequestDraft(storage, 'julien', { answers: {}, message: null }, 0)
    assert.equal(takeRequestDraft(storage, 'claire', 10), null)
    assert.equal(hasRequestDraft(storage, 'julien', DRAFT_LIFETIME_MS + 1), false)
    assert.equal(takeRequestDraft(storage, 'julien', DRAFT_LIFETIME_MS + 1), null)
  })

  it('survives a blocked or broken storage', () => {
    const blocked = {
      getItem: () => {
        throw new Error('blocked')
      },
      setItem: () => {
        throw new Error('blocked')
      },
      removeItem: () => {}
    }
    saveRequestDraft(blocked, 'julien', { answers: {} })
    assert.equal(takeRequestDraft(blocked, 'julien'), null)
    assert.equal(hasRequestDraft(blocked, 'julien'), false)
    assert.equal(takeRequestDraft(null, 'julien'), null)

    const broken = memoryStorage()
    broken.setItem('trial-request:julien', '{not json')
    assert.equal(takeRequestDraft(broken, 'julien'), null)
  })

  it('forgets every request draft at logout, and nothing else', () => {
    const storage = memoryStorage()
    saveRequestDraft(storage, 'julien', { answers: {} })
    saveRequestDraft(storage, 'claire', { answers: {} })
    storage.setItem('something-else', 'kept')
    clearRequestDrafts(storage)
    assert.deepEqual([...storage.items.keys()], ['something-else'])
    clearRequestDrafts(null)
  })
})
