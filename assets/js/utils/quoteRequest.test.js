import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import {
  buildQuoteMessage,
  hasRequiredAnswers,
  MAX_TRACKS,
  quoteAnswers,
  quoteContactOrigin,
  quoteDraftKey,
  quoteQuestions
} from './quoteRequest.js'

/**
 * « Demander un devis » (#1408). Run with `npm test`.
 */

const t = (key, params = {}) => {
  const values = Object.entries(params)
    .map(([name, value]) => `${name}=${value}`)
    .join(',')
  return values
    ? `${key.split('.').slice(-2).join('.')}(${values})`
    : key.split('.').slice(-2).join('.')
}
const questions = quoteQuestions('sound_engineering')

describe('quoteQuestions', () => {
  it('asks a sound engineer what, how many tracks, a link, when and the budget, in that order', () => {
    assert.deepEqual(
      questions.map((question) => question.key),
      ['need', 'tracks', 'link', 'deadline', 'budget']
    )
    assert.deepEqual(questions[0].options, ['mixing', 'mastering', 'recording', 'live'])
    assert.deepEqual(questions[4].options, ['under_100', '100_to_300', '300_to_1000', 'over_1000'])
  })

  it('asks a photographer and a videographer what, when, where and the budget (#1411)', () => {
    for (const [kind, needs] of [
      ['photography', ['concert', 'promo', 'cover']],
      ['video', ['clip', 'live_session', 'concert_film']]
    ]) {
      const asked = quoteQuestions(kind)
      assert.deepEqual(
        asked.map((question) => question.key),
        ['need', 'date', 'city', 'budget'],
        kind
      )
      assert.deepEqual(asked[0].options, needs, kind)
    }
  })

  it('asks a graphic designer and a webmaster what, a link, when and the budget, never where (#1412)', () => {
    for (const [kind, needs] of [
      ['graphic_design', ['cover', 'logo', 'poster', 'merch', 'visual_identity', 'social_media']],
      ['web', ['band_site', 'epk', 'online_store', 'redesign_maintenance']]
    ]) {
      const asked = quoteQuestions(kind)
      assert.deepEqual(
        asked.map((question) => question.key),
        ['need', 'link', 'deadline', 'budget'],
        kind
      )
      assert.deepEqual(asked[0].options, needs, kind)
    }
  })

  // Mixing stays a sound engineer's: a producer is asked about what they do (#1413).
  it('asks a producer what a sound engineer is asked, about any of what they do', () => {
    const asked = quoteQuestions('production')
    assert.deepEqual(
      asked.map((question) => question.key),
      ['need', 'tracks', 'link', 'deadline', 'budget']
    )
    assert.deepEqual(asked[0].options, [
      'beatmaking',
      'arrangement',
      'record_production',
      'composition',
      'topline'
    ])
  })

  it('asks nothing of a kind that takes no quote request', () => {
    assert.deepEqual(quoteQuestions('teaching'), [])
    assert.deepEqual(quoteQuestions('unknown'), [])
  })
})

describe('quoteAnswers', () => {
  it('keeps only the answers given, trimmed', () => {
    assert.deepEqual(
      quoteAnswers(
        {
          need: 'mixing',
          tracks: 5,
          link: ' https://soundcloud.com/demo ',
          deadline: '',
          budget: null
        },
        questions
      ),
      { need: 'mixing', tracks: 5, link: 'https://soundcloud.com/demo' }
    )
  })

  it('drops a number of tracks out of range, and an answer not asked', () => {
    assert.deepEqual(quoteAnswers({ need: 'live', tracks: 0, genre: 'jazz' }, questions), {
      need: 'live'
    })
    assert.deepEqual(quoteAnswers({ need: 'live', tracks: 2.5 }, questions), { need: 'live' })
    assert.deepEqual(quoteAnswers({ need: 'live', tracks: MAX_TRACKS + 1 }, questions), {
      need: 'live'
    })
    assert.deepEqual(quoteAnswers(null, questions), {})
  })

  // A draft kept before signing in may hold what a later build no longer offers.
  it('drops an option no longer offered and a day that is not a calendar day', () => {
    assert.deepEqual(
      quoteAnswers({ need: 'cooking', budget: 'free', deadline: '15/12/2026' }, questions),
      {}
    )
  })
})

describe('hasRequiredAnswers', () => {
  it('needs what is asked for, nothing else', () => {
    assert.equal(hasRequiredAnswers({}, questions), false)
    assert.equal(hasRequiredAnswers({ need: ' ' }, questions), false)
    assert.equal(hasRequiredAnswers({ need: 'mastering' }, questions), true)
  })
})

describe('quoteContactOrigin', () => {
  it('names the service and carries the answers given', () => {
    assert.deepEqual(
      quoteContactOrigin('s1', { need: 'recording', budget: 'under_100', link: '' }, questions),
      {
        serviceId: 's1',
        quote: { need: 'recording', budget: 'under_100' }
      }
    )
  })
})

describe('quoteDraftKey', () => {
  it('keeps a quote request apart from a lesson request to the same member', () => {
    assert.equal(quoteDraftKey('sound_engineering', 'julien'), 'quote:sound_engineering:julien')
  })
})

describe('buildQuoteMessage', () => {
  const formatDate = (day) => `day(${day})`

  it('writes one line per answer, in asking order', () => {
    const message = buildQuoteMessage(
      {
        budget: '100_to_300',
        deadline: '2026-12-15',
        link: 'https://x.test',
        tracks: 5,
        need: 'mixing'
      },
      { name: 'Julien', questions, formatDate },
      t
    )
    assert.equal(
      message,
      [
        'message.greeting(name=Julien)',
        'message.need(value=need.mixing)',
        'message.tracks(value=5)',
        'message.link(value=https://x.test)',
        'message.deadline(value=day(2026-12-15))',
        'message.budget(value=budget.100_to_300)'
      ].join('\n')
    )
  })

  it('greets alone before any answer, and skips an option it does not know', () => {
    assert.equal(
      buildQuoteMessage({ need: 'cooking' }, { name: 'Julien', questions, formatDate }, t),
      'message.greeting(name=Julien)'
    )
  })
})

describe('the quote of a photographer (#1411)', () => {
  const photography = quoteQuestions('photography')

  it('keeps a calendar day and a city, trimmed, and drops a city too long', () => {
    assert.deepEqual(
      quoteAnswers({ need: 'concert', date: '2026-12-12', city: ' Lyon ', tracks: 3 }, photography),
      { need: 'concert', date: '2026-12-12', city: 'Lyon' }
    )
    assert.deepEqual(quoteAnswers({ need: 'cover', city: 'a'.repeat(101) }, photography), {
      need: 'cover'
    })
  })

  it('names the day and the place in its message', () => {
    assert.equal(
      buildQuoteMessage(
        { need: 'promo', city: 'Lyon', date: '2026-12-12' },
        { name: 'Léa', questions: photography, formatDate: (day) => `day(${day})` },
        t
      ),
      [
        'message.greeting(name=Léa)',
        'message.need(value=need.promo)',
        'message.date(value=day(2026-12-12))',
        'message.city(value=Lyon)'
      ].join('\n')
    )
  })
})

describe('the quote of a producer (#1413)', () => {
  const production = quoteQuestions('production')

  it('names what is asked of them in its message, and drops a sound engineer need', () => {
    assert.equal(
      buildQuoteMessage(
        { need: 'topline', tracks: 1, link: 'https://x.test/instru' },
        { name: 'Sam', questions: production, formatDate: (day) => `day(${day})` },
        t
      ),
      [
        'message.greeting(name=Sam)',
        'message.need(value=need.topline)',
        'message.tracks(value=1)',
        'message.link(value=https://x.test/instru)'
      ].join('\n')
    )
    assert.deepEqual(quoteAnswers({ need: 'mixing', tracks: 2 }, production), { tracks: 2 })
  })
})
