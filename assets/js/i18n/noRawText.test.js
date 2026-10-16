import assert from 'node:assert/strict'
import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, it } from 'node:test'
import { parse } from 'vue/compiler-sfc'

// Components whose text lives in the catalogue, by file or by folder (a trailing slash). A path joins
// this list once it is migrated (#1182), and from then on a hardcoded string in a template fails
// here. Strings built in <script> are not seen, so those stay a review concern.
const MIGRATED_PATHS = [
  'components/Announce/',
  'components/AppAdminLayout.vue',
  'components/AppBandLayout.vue',
  'components/AppBaseLayout.vue',
  'components/AppNavbarUserCluster.vue',
  'components/Auth/AuthRequiredModal.vue',
  'components/Auth/SignupOptions.vue',
  'components/BandSpace/Agenda/',
  'components/BandSpace/BandSidebar.vue',
  'components/BandSpace/BandSpaceSearchResultRow.vue',
  'components/BandSpace/BandSpaceSearchResults.vue',
  'components/BandSpace/BandSpaceSelector.vue',
  'components/BandSpace/Chat/',
  'components/BandSpace/CommandPalette.vue',
  'components/BandSpace/CreateBandSpaceModal.vue',
  'components/BandSpace/Dashboard/',
  'components/BandSpace/Presentation/',
  'components/BandSpace/Settings/',
  'components/Comment/',
  'components/Course/',
  'components/Discover/',
  'components/Editor/',
  'components/Home/',
  'components/Message/',
  'components/Notification/',
  'components/Publication/AddDiscoverModal.vue',
  'components/Publication/PublicationDetailLayout.vue',
  'components/Publication/PublicationSettingsPanel.vue',
  'components/Publication/PublicationShareBar.vue',
  'components/Publication/Sidebar/',
  'components/Publication/VoteButtons.vue',
  'components/Publication/VoteButtonsList.vue',
  'components/Search/',
  'components/ShareButton.vue',
  'components/SkipLink.vue',
  'components/publication/',
  'views/BandSpace/Agenda.vue',
  'views/BandSpace/Chat.vue',
  'views/BandSpace/Index.vue',
  'views/BandSpace/InvitationResponse.vue',
  'views/BandSpace/NoSpace.vue',
  'views/BandSpace/Presentation.vue',
  'views/BandSpace/Settings.vue',
  'views/Course/',
  'views/Discover/',
  'views/Gallery/',
  'views/Global/Breadcrumb.vue',
  'views/Global/Footer.vue',
  'views/Global/Menu.vue',
  'views/Global/MenuBand.vue',
  'views/Home/',
  'views/Message/',
  'views/Notification/',
  'views/Publication/',
  'views/Search/',
  'views/User/Course/',
  'views/User/Gallery/',
  'views/User/Publication/'
]

// The footer wordmark is a logo spelled out in two spans, not text to translate.
const ALLOWED_TEXT = new Set(['MUSIC', 'ALL'])
const TEXT_ATTRIBUTES = new Set(['alt', 'aria-label', 'header', 'label', 'placeholder', 'title'])
const NODE = { ELEMENT: 1, TEXT: 2, ATTRIBUTE: 6, DIRECTIVE: 7 }
const LETTER = /\p{L}/u
const STRING_LITERAL = /^\s*(['"`]).*\1\s*$/s

function rawTextIn(node) {
  const found = []

  if (node.type === NODE.TEXT) {
    const text = node.content.trim()
    if (LETTER.test(text) && !ALLOWED_TEXT.has(text)) found.push(text)
  }

  for (const prop of node.props ?? []) {
    if (prop.type === NODE.ATTRIBUTE && TEXT_ATTRIBUTES.has(prop.name)) {
      if (LETTER.test(prop.value?.content ?? '')) found.push(`${prop.name}="${prop.value.content}"`)
    }
    // :label="'Texte'" and v-tooltip="'Texte'" are hardcoded strings too.
    const isTextBinding = prop.name === 'bind' && TEXT_ATTRIBUTES.has(prop.arg?.content)
    if (prop.type === NODE.DIRECTIVE && (isTextBinding || prop.name === 'tooltip')) {
      const expression = prop.exp?.content ?? ''
      if (STRING_LITERAL.test(expression) && LETTER.test(expression)) found.push(expression)
    }
  }

  return found.concat((node.children ?? []).flatMap(rawTextIn))
}

function rawTextInComponent(source) {
  const { descriptor } = parse(source)

  return descriptor.template ? rawTextIn(descriptor.template.ast) : []
}

describe('rawTextInComponent', () => {
  it('finds text, text attributes and literal bindings', () => {
    const source = `<template>
      <div aria-label="Fermer" :title="'Ouvrir'" v-tooltip="'Aide'">
        Bonjour
        <Button label="Envoyer" class="p-2" />
      </div>
    </template>`

    assert.deepEqual(rawTextInComponent(source), [
      'aria-label="Fermer"',
      "'Ouvrir'",
      "'Aide'",
      'Bonjour',
      'label="Envoyer"'
    ])
  })

  it('accepts translated text, expressions and the wordmark', () => {
    const source = `<template>
      <div :aria-label="t('a.b')" :title="item.label" v-tooltip="t('a.c')" class="flex gap-2">
        {{ t('a.d') }} {{ count }}
        <span>MUSIC</span>ALL
      </div>
    </template>`

    assert.deepEqual(rawTextInComponent(source), [])
  })
})

const SOURCE_ROOT = join(import.meta.dirname, '..')

function isMigrated(path) {
  return MIGRATED_PATHS.some((migrated) =>
    migrated.endsWith('/') ? path.startsWith(migrated) : path === migrated
  )
}

const migratedComponents = readdirSync(SOURCE_ROOT, { recursive: true })
  .filter((path) => path.endsWith('.vue') && isMigrated(path))
  .sort()

describe('migrated components', () => {
  it('covers every path on the list', () => {
    const unmatched = MIGRATED_PATHS.filter(
      (migrated) =>
        !migratedComponents.some((path) => isMigrated(path) && path.startsWith(migrated))
    )

    assert.deepEqual(unmatched, [])
  })

  for (const path of migratedComponents) {
    it(`${path} has no hardcoded text in its template`, () => {
      const source = readFileSync(join(SOURCE_ROOT, path), 'utf8')

      assert.deepEqual(rawTextInComponent(source), [])
    })
  }
})
