import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { parseInlineFieldValue } from './inlineFieldValue.js'

describe('parseInlineFieldValue', () => {
  it('trims text, and clears an optional field left empty', () => {
    assert.deepEqual(parseInlineFieldValue('  Em  '), { value: 'Em' })
    assert.deepEqual(parseInlineFieldValue('   '), { value: null })
  })

  it('refuses a required field left empty', () => {
    assert.deepEqual(parseInlineFieldValue(' ', { required: true }), {
      error: 'Ce champ est obligatoire.'
    })
  })

  it('reads a whole number and nothing else', () => {
    assert.deepEqual(parseInlineFieldValue('07', { kind: 'number' }), { value: 7 })
    for (const text of ['1e3', '3.5', '-4', '12 bpm']) {
      assert.deepEqual(parseInlineFieldValue(text, { kind: 'number' }), {
        error: 'Indiquez un nombre entier.'
      })
    }
  })

  it('reads a duration as seconds, and keeps its error', () => {
    assert.deepEqual(parseInlineFieldValue('3:47', { kind: 'duration' }), { value: 227 })
    assert.ok(parseInlineFieldValue('trois minutes', { kind: 'duration' }).error)
  })
})
