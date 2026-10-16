import assert from 'node:assert/strict'
import { readdirSync, readFileSync } from 'node:fs'
import { basename, join } from 'node:path'
import { describe, it } from 'node:test'
import fr from './fr/index.js'
import layout from './fr/layout.json' with { type: 'json' }
import { frenchPluralIndex, i18n } from './index.js'

const SOURCE_ROOT = join(import.meta.dirname, '..')
const CATALOGUE_DIRECTORY = join(import.meta.dirname, 'fr')
const PLACEHOLDER = /\{(\w+)\}/g
const KEY = '[a-z0-9_]+(?:\\.[a-z0-9_]+)+'
// Best effort: a single-quoted dotted key as the first argument of t() or $t(), or the keypath of
// <i18n-t>. Dynamic keys are not checked, which is why components map values to literal keys.
const KEY_CALLS = [
  new RegExp(`\\$?\\bt\\(\\s*'(${KEY})'`, 'g'),
  new RegExp(`keypath="(${KEY})"`, 'g')
]
// A key held in a map (`labelKey: 'a.b'`) and translated where it is shown counts as asked for.
const KEY_LITERAL = new RegExp(`'(${KEY})'`, 'g')

function flatten(messages, prefix = '') {
  return Object.entries(messages).flatMap(([key, value]) =>
    typeof value === 'object' ? flatten(value, `${prefix}${key}.`) : [[`${prefix}${key}`, value]]
  )
}

function sourceFiles(directory) {
  return readdirSync(directory, { withFileTypes: true, recursive: true })
    .filter((entry) => entry.isFile() && /\.(vue|js)$/.test(entry.name))
    .filter((entry) => !entry.name.endsWith('.test.js'))
    .map((entry) => join(entry.parentPath, entry.name))
}

// The count that selects each form of a plural message under the French rule.
function choiceForForm(formIndex, formCount) {
  return formCount === 2 ? formIndex + 1 : formIndex
}

function keysIn(source, patterns) {
  return patterns.flatMap((pattern) => [...source.matchAll(pattern)].map(([, key]) => key))
}

const entries = flatten(fr)
const sources = sourceFiles(SOURCE_ROOT).map((file) => ({
  file,
  source: readFileSync(file, 'utf8')
}))

describe('the French catalogue', () => {
  // vue-i18n reads @, |, { and } as syntax, so a message containing one would render differently
  // from what was written. Rendering each placeholder as itself must give the raw text back, form
  // by form for a plural message.
  it('renders every message exactly as written', () => {
    for (const [key, message] of entries) {
      const params = Object.fromEntries(
        [...message.matchAll(PLACEHOLDER)].map(([placeholder, name]) => [name, placeholder])
      )
      const forms = message.split('|').map((form) => form.trim())

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

  it('loads every area file as the namespace it is named after', () => {
    const areaFiles = readdirSync(CATALOGUE_DIRECTORY).filter(
      (name) => name.endsWith('.json') && name !== 'layout.json'
    )

    for (const name of areaFiles) {
      const namespace = basename(name, '.json')
      const messages = JSON.parse(readFileSync(join(CATALOGUE_DIRECTORY, name), 'utf8'))
      assert.deepEqual(fr[namespace], messages, name)
      assert.ok(!(namespace in layout), `layout.json already has a "${namespace}" namespace`)
    }
  })

  it('has every key the components ask for', () => {
    const known = new Set(entries.map(([key]) => key))
    const missing = sources.flatMap(({ file, source }) =>
      keysIn(source, KEY_CALLS)
        .filter((key) => !known.has(key))
        .map((key) => `${file}: ${key}`)
    )

    assert.deepEqual(missing, [])
  })

  it('has no key that no component asks for', () => {
    const used = new Set(
      sources.flatMap(({ source }) => keysIn(source, [...KEY_CALLS, KEY_LITERAL]))
    )

    assert.deepEqual(
      entries.map(([key]) => key).filter((key) => !used.has(key)),
      []
    )
  })
})

describe('frenchPluralIndex', () => {
  it('makes 0 and 1 singular in a two form message', () => {
    assert.deepEqual(
      [0, 1, 2, 10].map((count) => frenchPluralIndex(count, 2)),
      [0, 0, 1, 1]
    )
  })

  it('picks zero, one or many in a three form message', () => {
    assert.deepEqual(
      [0, 1, 2, 10].map((count) => frenchPluralIndex(count, 3)),
      [0, 1, 2, 2]
    )
  })

  it('reads a negative count by its size', () => {
    assert.equal(frenchPluralIndex(-3, 2), 1)
  })
})
