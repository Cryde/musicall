import assert from 'node:assert/strict'
import { describe, it } from 'node:test'
import { readMentionParts, serializeEditor, toWireFormat } from './mentionEditor.js'

/**
 * The composer is a contenteditable, and there is no DOM in these tests, so the walk is exercised
 * against the node shape it actually reads: `nodeType`, `nodeValue`, `childNodes`, `dataset`,
 * `tagName`. That is the whole reason it was written against those five and nothing else.
 */
const text = (value) => ({ nodeType: 3, nodeValue: value })
const chip = (userId) => ({ nodeType: 1, tagName: 'SPAN', dataset: { mentionId: userId } })
const el = (tagName, ...childNodes) => ({ nodeType: 1, tagName, dataset: {}, childNodes })
const root = (...childNodes) => ({ childNodes })

const ALICE = '550e8400-e29b-41d4-a716-446655440000'
const BOB = '6ba7b810-9dad-11d1-80b4-00c04fd430c8'

describe('readMentionParts', () => {
  it('reads an empty composer as nothing', () => {
    assert.deepEqual(readMentionParts(root()), [])
  })

  it('reads plain text', () => {
    assert.deepEqual(readMentionParts(root(text('on répète mardi'))), [
      { type: 'text', value: 'on répète mardi' }
    ])
  })

  it('reads a chip as its member, not as what it renders', () => {
    assert.deepEqual(readMentionParts(root(text('salut '), chip(ALICE))), [
      { type: 'text', value: 'salut ' },
      { type: 'mention', userId: ALICE }
    ])
  })

  it('keeps the order of text and chips', () => {
    assert.deepEqual(readMentionParts(root(chip(ALICE), text(' et '), chip(BOB), text(' à 20h'))), [
      { type: 'mention', userId: ALICE },
      { type: 'text', value: ' et ' },
      { type: 'mention', userId: BOB },
      { type: 'text', value: ' à 20h' }
    ])
  })

  it('reads two adjacent chips as two mentions', () => {
    assert.deepEqual(readMentionParts(root(chip(ALICE), chip(BOB))), [
      { type: 'mention', userId: ALICE },
      { type: 'mention', userId: BOB }
    ])
  })

  it('reads a br as a newline', () => {
    assert.deepEqual(readMentionParts(root(text('une'), el('BR'), text('deux'))), [
      { type: 'text', value: 'une' },
      { type: 'text', value: '\n' },
      { type: 'text', value: 'deux' }
    ])
  })

  it('recurses into a div the browser inserted, and starts a line for it', () => {
    // Enter and paste both make these whatever the key handlers do, and dropping them would eat text.
    assert.deepEqual(readMentionParts(root(text('une'), el('DIV', text('deux')))), [
      { type: 'text', value: 'une' },
      { type: 'text', value: '\n' },
      { type: 'text', value: 'deux' }
    ])
  })

  it('does not open with a newline when the first thing is a block', () => {
    assert.deepEqual(readMentionParts(root(el('DIV', text('une')))), [
      { type: 'text', value: 'une' }
    ])
  })

  it('does not stack newlines when blocks nest', () => {
    // `<div>une</div><div><div>deux</div></div>` is two lines on screen, not three, and the recursion
    // gives exactly that: each call starts its own `parts`, so the inner block is the first thing in
    // its own walk and adds nothing. Pinned because the `parts.length > 0` test that does it reads
    // like an off-by-one guard rather than the rule it is.
    assert.deepEqual(
      readMentionParts(root(el('DIV', text('une')), el('DIV', el('DIV', text('deux'))))),
      [
        { type: 'text', value: 'une' },
        { type: 'text', value: '\n' },
        { type: 'text', value: 'deux' }
      ]
    )
  })

  it('finds a chip nested inside a pasted block', () => {
    assert.deepEqual(readMentionParts(root(el('DIV', text('salut '), chip(ALICE)))), [
      { type: 'text', value: 'salut ' },
      { type: 'mention', userId: ALICE }
    ])
  })

  it('keeps the text of an inline element it does not know', () => {
    assert.deepEqual(readMentionParts(root(el('B', text('gras')))), [
      { type: 'text', value: 'gras' }
    ])
  })

  it('skips a comment node rather than choking on it', () => {
    assert.deepEqual(readMentionParts(root({ nodeType: 8 }, text('après'))), [
      { type: 'text', value: 'après' }
    ])
  })
})

describe('toWireFormat', () => {
  it('writes a mention as the stored token', () => {
    assert.equal(
      toWireFormat([
        { type: 'text', value: 'salut ' },
        { type: 'mention', userId: ALICE }
      ]),
      `salut @[${ALICE}]`
    )
  })

  it('writes the everyone chip as its sentinel', () => {
    assert.equal(toWireFormat([{ type: 'mention', userId: 'tous' }]), '@[tous]')
  })

  it('writes nothing for nothing', () => {
    assert.equal(toWireFormat([]), '')
  })
})

describe('serializeEditor', () => {
  it('turns a composer full of chips into what the API stores', () => {
    assert.equal(
      serializeEditor(
        root(chip('tous'), text(' répète annulée'), el('BR'), chip(BOB), text(' ok ?'))
      ),
      `@[tous] répète annulée\n@[${BOB}] ok ?`
    )
  })
})
