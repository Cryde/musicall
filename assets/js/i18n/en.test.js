import assert from 'node:assert/strict'
import { after, before, describe, it } from 'node:test'
import en from './en/index.js'
import fr from './fr/index.js'
import { i18n } from './index.js'
import { punctuationViolations } from './plainPunctuation.js'

const PLACEHOLDER = /\{(\w+)\}/g

function flatten(messages, prefix = '') {
  return Object.entries(messages).flatMap(([key, value]) =>
    typeof value === 'object' ? flatten(value, `${prefix}${key}.`) : [[`${prefix}${key}`, value]]
  )
}

function placeholdersOf(message) {
  return [...new Set([...message.matchAll(PLACEHOLDER)].map(([, name]) => name))].sort()
}

function formsOf(message) {
  return message.split('|').map((form) => form.trim())
}

// The count that selects each form of a plural message under the English rule, vue-i18n's default:
// two forms are one | other, three are zero | one | other.
function choiceForForm(formIndex, formCount) {
  return formCount === 2 ? formIndex + 1 : formIndex
}

const entries = flatten(en)
const french = new Map(flatten(fr))
// A key French lacks is reported once, by the first test, rather than breaking the comparisons.
const entriesFrenchHas = entries.filter(([key]) => french.has(key))

// Missing keys are expected until #1194: they show in French. What is here must be right.
describe('the English catalogue', () => {
  before(() => {
    i18n.global.setLocaleMessage('en', en)
    i18n.global.locale.value = 'en'
  })

  after(() => {
    i18n.global.locale.value = 'fr'
  })

  it('only has keys the French catalogue has', () => {
    assert.deepEqual(
      entries.map(([key]) => key).filter((key) => !french.has(key)),
      []
    )
  })

  it('keeps the placeholders of the French message', () => {
    for (const [key, message] of entriesFrenchHas) {
      assert.deepEqual(placeholdersOf(message), placeholdersOf(french.get(key)), key)
    }
  })

  it('has as many plural forms as the French message', () => {
    for (const [key, message] of entriesFrenchHas) {
      assert.equal(formsOf(message).length, formsOf(french.get(key)).length, key)
    }
  })

  // vue-i18n reads @, |, { and } as syntax, so a message containing one would render differently
  // from what was written. Rendering each placeholder as itself must give the raw text back, form
  // by form for a plural message.
  it('renders every message exactly as written', () => {
    for (const [key, message] of entries) {
      const params = Object.fromEntries(
        [...message.matchAll(PLACEHOLDER)].map(([placeholder, name]) => [name, placeholder])
      )
      const forms = formsOf(message)

      // A fresh params object per call: vue-i18n writes the plural count into the one it is given.
      forms.forEach((form, index) => {
        const rendered =
          forms.length === 1
            ? i18n.global.t(key, params)
            : i18n.global.t(key, { ...params }, choiceForForm(index, forms.length))
        assert.equal(rendered, form, key)
      })
    }
  })

  it('uses plain punctuation', () => {
    const violations = entries.flatMap(([key, message]) =>
      punctuationViolations(message).map((violation) => `${key}: ${violation}`)
    )

    assert.deepEqual(violations, [])
  })
})

// English needs no rule of its own: unlike French, 0 takes the plural form, which is vue-i18n's default.
describe('the English plural rule', () => {
  const PLURAL_MESSAGES = {
    two_forms: 'one member | {n} members',
    three_forms: 'no members | one member | {n} members'
  }

  before(() => {
    i18n.global.mergeLocaleMessage('en', { plural_rule_test: PLURAL_MESSAGES })
    i18n.global.mergeLocaleMessage('fr', { plural_rule_test: PLURAL_MESSAGES })
  })

  function render(key, counts, locale) {
    return counts.map((count) => i18n.global.t(`plural_rule_test.${key}`, count, { locale }))
  }

  it('makes 0 plural and 1 singular in a two form message', () => {
    assert.deepEqual(render('two_forms', [0, 1, 2, 10], 'en'), [
      '0 members',
      'one member',
      '2 members',
      '10 members'
    ])
  })

  it('picks zero, one or many in a three form message', () => {
    assert.deepEqual(render('three_forms', [0, 1, 2, 10], 'en'), [
      'no members',
      'one member',
      '2 members',
      '10 members'
    ])
  })

  it('differs from French, where 0 is singular', () => {
    assert.deepEqual(render('two_forms', [0], 'fr'), ['one member'])
  })

  it('was checked for the two and three form messages the French catalogue sticks to', () => {
    const formCounts = new Set(
      [...french.values()].map((message) => formsOf(message).length).filter((count) => count > 1)
    )

    assert.deepEqual([...formCounts].sort(), [2, 3])
  })
})
