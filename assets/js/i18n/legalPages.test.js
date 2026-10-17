import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, it } from 'node:test'
import { parse } from 'vue/compiler-sfc'

// The legal pages are written once per language rather than through the catalogue (#1195): the
// French file stays where it was, its English version sits in en/ under the same name.
const LEGAL_DIRECTORY = join(import.meta.dirname, '..', 'views', 'Legal')
const PAGES = ['AccountDeletion.vue', 'MentionsLegales.vue', 'Privacy.vue', 'Terms.vue']
const NODE = { ELEMENT: 1, TEXT: 2, ATTRIBUTE: 6, DIRECTIVE: 7 }
// English text is plain ASCII plus accented Latin letters (Jérémy, Évêque): no typographic dashes,
// quotes or ellipsis, no arrow, middle dot, multiplication sign or non-breaking space.
const NOT_PLAIN = /[^\t\n\r\x20-\x7E\u00C0-\u017F]|[\u00D7\u00F7]/gu
// French puts a space before : ; ! ? and English does not.
const SPACE_BEFORE_PUNCTUATION = /\s[:;!?]/

function attributeOf(prop) {
  if (prop.type === NODE.ATTRIBUTE) return `${prop.name}="${prop.value?.content ?? ''}"`
  const argument = prop.arg ? `:${prop.arg.content}` : ''

  return `v-${prop.name}${argument}="${prop.exp?.content ?? ''}"`
}

function elementsIn(node) {
  const children = (node.children ?? []).flatMap(elementsIn)
  if (node.type !== NODE.ELEMENT) return children

  return [{ tag: node.tag, attributes: node.props.map(attributeOf) }, ...children]
}

function templateElements(source) {
  return elementsIn(parse(source).descriptor.template.ast).slice(1)
}

/** Every element of a template, its attributes and bindings, in order, without the text. */
function markupOutline(source) {
  return templateElements(source).map(({ tag, attributes }) => [tag, ...attributes].join(' '))
}

/** The static ids of a template, which links and the router's hash scrolling target. */
function anchorIds(source) {
  return templateElements(source)
    .flatMap(({ attributes }) => attributes)
    .filter((attribute) => attribute.startsWith('id="'))
    .map((attribute) => attribute.slice('id="'.length, -1))
}

function textsIn(node) {
  const texts = (node.children ?? []).flatMap(textsIn)

  return node.type === NODE.TEXT ? [node.content, ...texts] : texts
}

/** The texts of a template with a space before : ; ! ?, attributes and bindings (`:to=`) aside. */
function spacedPunctuation(source) {
  return textsIn(parse(source).descriptor.template.ast).filter((text) =>
    SPACE_BEFORE_PUNCTUATION.test(text)
  )
}

function readPage(...path) {
  return readFileSync(join(LEGAL_DIRECTORY, ...path), 'utf8')
}

describe('markupOutline', () => {
  it('keeps elements, attributes and bindings, drops text, interpolations and comments', () => {
    const source = `<template>
      <div>
        <!-- a comment -->
        <p id="recours" class="mt-4">Texte {{ date }} <strong>gras</strong></p>
        <router-link :to="{ name: 'app_privacy' }">lien</router-link>
      </div>
    </template>`

    assert.deepEqual(markupOutline(source), [
      'p id="recours" class="mt-4"',
      'strong',
      `router-link v-bind:to="{ name: 'app_privacy' }"`
    ])
    assert.deepEqual(anchorIds(source), ['recours'])
  })
})

describe('plain English checks', () => {
  it('accept ASCII and accented Latin letters, and refuse typographic characters', () => {
    const typographic = String.fromCodePoint(0x2014, 0x2013, 0x2026, 0x2019, 0x201c, 0xab, 0xb7)
    const invisibleOrSymbol = String.fromCodePoint(0x2192, 0xa0, 0x202f, 0x2009, 0xd7)

    assert.equal(`Jérémy, 8 rue de la Ville l'Évêque: "MusicAll" (5 GB)`.match(NOT_PLAIN), null)
    assert.deepEqual(`a${typographic}${invisibleOrSymbol}b`.match(NOT_PLAIN), [
      ...typographic,
      ...invisibleOrSymbol
    ])
  })

  it('find a space before : ; ! ? in the text, not in a binding', () => {
    const source = `<template>
      <p>Contact : <router-link :to="{ name: 'app_privacy' }">policy</router-link>. Note: fine</p>
    </template>`

    assert.deepEqual(spacedPunctuation(source), ['Contact : '])
  })
})

describe('English legal pages', () => {
  for (const page of PAGES) {
    describe(page, () => {
      const french = readPage(page)
      const english = readPage('en', page)

      it('keeps the anchor ids of the French version', () => {
        assert.deepEqual(anchorIds(english), anchorIds(french))
      })

      it('mirrors the markup of the French version, links and addresses included', () => {
        assert.deepEqual(markupOutline(english), markupOutline(french))
      })

      it('uses plain characters only', () => {
        assert.deepEqual(english.match(NOT_PLAIN), null)
      })

      it('puts no space before : ; ! ?', () => {
        assert.deepEqual(spacedPunctuation(english), [])
      })
    })
  }

  it('keeps the #recours anchor that a report decision links to', () => {
    assert.ok(anchorIds(readPage('en', 'Terms.vue')).includes('recours'))
  })
})
