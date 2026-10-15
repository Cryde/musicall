import assert from 'node:assert/strict'
import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, it } from 'node:test'
import { createI18n } from 'vue-i18n'
import fr from './fr.json' with { type: 'json' }

const SOURCE_ROOT = join(import.meta.dirname, '..')
const PLACEHOLDER = /\{(\w+)\}/g
// Best effort: a single-quoted dotted key as the first argument of t() or $t(). Dynamic keys are not checked.
const KEY_CALL = /\$?\bt\(\s*'([a-z0-9_]+(?:\.[a-z0-9_]+)+)'/g

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

const entries = flatten(fr)

describe('the French catalogue', () => {
  // vue-i18n reads @, |, { and } as syntax, so a message containing one would render differently
  // from what was written. Rendering each placeholder as itself must give the raw text back.
  it('renders every message exactly as written', () => {
    const i18n = createI18n({ legacy: false, locale: 'fr', messages: { fr } })

    for (const [key, message] of entries) {
      const params = Object.fromEntries(
        [...message.matchAll(PLACEHOLDER)].map(([placeholder, name]) => [name, placeholder])
      )
      assert.equal(i18n.global.t(key, params), message, key)
    }
  })

  const usedKeys = sourceFiles(SOURCE_ROOT).flatMap((file) =>
    [...readFileSync(file, 'utf8').matchAll(KEY_CALL)].map(([, key]) => ({ file, key }))
  )

  it('has every key the components ask for', () => {
    const known = new Set(entries.map(([key]) => key))
    const missing = usedKeys
      .filter(({ key }) => !known.has(key))
      .map(({ file, key }) => `${file}: ${key}`)

    assert.deepEqual(missing, [])
  })

  it('has no key that no component asks for', () => {
    const used = new Set(usedKeys.map(({ key }) => key))

    assert.deepEqual(
      entries.map(([key]) => key).filter((key) => !used.has(key)),
      []
    )
  })
})
